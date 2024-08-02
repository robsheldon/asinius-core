<?php

/*******************************************************************************
*                                                                              *
*   Asinius\IOBuffer                                                           *
*                                                                              *
*   Store data as a bytestream and read it back as bytes, chars, or lines.     *
*                                                                              *
*   Applications can switch read modes at any time (although it is a bit       *
*   expensive to do so). IOBuffers will store data up to the limits set by     *
*   the application or caller, so they can be rewound as needed.               *
*                                                                              *
*   IOBuffers do not handle any device I/O themselves, but they make it        *
*   easier for other classes to provide an interface between I/O devices       *
*   and the application.                                                       *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2024 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius;

use Closure;
use RuntimeException;

class IOBuffer
{

    protected        ?Closure $_callback        = null;
    protected         mixed   $_storage         = null;
    protected        ?int     $_lock            = null;
    protected         int     $_read_index      = 0;
    protected         int     $_storage_bytes   = 0;
    protected         array   $_newlines        = [];


    /**
     * Acquire a lock on the storage resource. Resource locking allows multiple
     * callers to share the same IOBuffer. Exclusive locks are used for both read
     * and write operations because read operations may juggle the resource file
     * pointer a bit.
     *
     * flock() doesn't work on "php://temp" resources, as it turns out, so a crude
     * internal lock is used here instead.
     *
     * @throws RuntimeException
     *
     * @return int
     */
    protected function _lock (): int
    {
        $new_lock = rand(1, 0xFFFF);
        for ( $i = 0; $i < 100; $i++ ) {
            //  This is not quite atomic, but should be a very narrow window for
            //  race conditions. There is no solution in PHP that doesn't use the
            //  filesystem or add additional dependencies (sem_* functions).
            if ( ($this->_lock = $this->_lock ?? $new_lock) === $new_lock ) {
                return $new_lock;
            }
            usleep(1);
        }
        throw new RuntimeException('Could not acquire a lock on internal storage in 100μs');
    }


    /**
     * Release a previously-acquired internal lock.
     *
     * @param  int  $lock_value
     *
     * @throws RuntimeException
     *
     * @return void
     */
    protected function _unlock (int $lock_value): void
    {
        if ( $this->_lock === $lock_value ) {
            $this->_lock = null;
        }
    }


    /**
     * Return a new IOBuffer.
     *
     * A Closure may be provided as a callback function. This function will be
     * called during any peek() or read() operation that would run past the end
     * of the current internal cache. The callback function is expected to call
     * append() on this object to add more data to the internal cache.
     *
     * $callback will be called as $callback(IOBUffer $this, int $needed_count)
     *
     * A Closure is required because a Callback type is not permitted as an
     * object property. See also https://wiki.php.net/rfc/typed_properties_v2#supported_types.
     * Use Closure::fromCallable() to convert a callable into a valid Closure.
     */
    public function __construct (?Closure $callback = null)
    {
        $this->_callback = $callback;
        $this->_storage = fopen('php://temp', 'a+b');
        @fseek($this->_storage, 0);
    }


    /**
     * Append some raw data to this IOBuffer.
     *
     * Code that interfaces with an i/o device would use this to append data to
     * their IOBuffer after reading it from the device.
     *
     * @param string $data
     *
     * @return void
     */
    public function append (string $data): void
    {
        $lock = $this->_lock();
        $written = fwrite($this->_storage, $data);
        if ( $written !== strlen($data) ) {
            throw new RuntimeException('Incomplete write to internal storage');
        }
        //  Update internal tracking of newlines, used by get_position().
        preg_match_all("/\n/", $data, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $this->_newlines[] = $this->_storage_bytes + $match[1];
        }
        $this->_storage_bytes += $written;
        $this->_unlock($lock);
    }


    /**
     * Retrieve $count bytes from the IOBuffer without moving the internal read
     * index. i.e., two consecutive peek() operations will return the same data.
     *
     * If insufficient data is available in the IOBuffer to fulfill the request,
     * then it will try to call the callback function provided by the caller in
     * IOBuffer's constructor. If that is not available or does not append()
     * data to the IOBuffer, then peek() will return whatever data is available.
     *
     * peek() can also accept a negative value, which will cause it to look
     * backwards through the IOBuffer and return ($count * -1) bytes back from
     * the current position, up to the beginning of the IOBuffer.
     *
     * @param int $count
     *
     * @return string
     */
    public function peek (int $count = 1): string
    {
        if ( $count < 0 ) {
            //  Try to look backwards in the buffer.
            $lock = $this->_lock();
            @fseek($this->_storage, max(0, $this->_read_index + $count));
            $byte_count = min($count * -1, $this->_read_index);
            if ( $byte_count > 0 ) {
                $out = @fread($this->_storage, min($count * -1, $this->_read_index));
            }
            else {
                $out = '';
            }
            @fseek($this->_storage, $this->_read_index);
            $this->_unlock($lock);
            return $out;
        }
        if ( $count === 0 ) {
            return '';
        }
        $last_size = -1;
        while ( true ) {
            //  Estimate how much data is still needed.
            $remaining = $count - $this->_storage_bytes + $this->_read_index;
            //  1. Return "" if:
            //      a. No data is available and no data was returned by the callback.
            //  2. Return some or all of the buffer if:
            //      a. The requested data is already in the buffer;
            //      b. Some data was returned by the callback but no more is available;
            //      c. Data has been returned by the calback and it satisfies the request.
            if ( $last_size ===  $this->_storage_bytes || $remaining <= 0 ) {
                if ( $remaining === $count ) {
                    //  No more data available (at this time?).
                    //  Signal that peek() failed to retrieve any data.
                    return '';
                }
                //  Return whatever is in the buffer.
                $lock = $this->_lock();
                @fseek($this->_storage, $this->_read_index);
                $out = @fread($this->_storage, min($count, $this->_storage_bytes - $this->_read_index));
                @fseek($this->_storage, $this->_read_index);
                $this->_unlock($lock);
                return $out;
            }
            //  Ask the caller to try append()ing a $remaining amount of data,
            //  probably through a read() on their i/o device.
            //  $remaining might not be in bytes! But, this will get called
            //  repeatedly until the peek() request is fulfilled, so we're okay.
            $this->_callback?->__invoke($this, $remaining);
            $last_size = $this->_storage_bytes;
        }
    }


    /**
     * Return the contents of the IOBuffer from its current read position up to
     * and including the next "\n", but do not change the read position.
     *
     * @return string
     */
    public function peek_line (): string
    {
        $out = '';
        $last_size = -1;
        $saved_index = $this->_read_index;
        $lock = $this->_lock();
        @fseek($this->_storage, $this->_read_index);
        while ( true ) {
            $chunk = @fgets($this->_storage);
            $out .= $chunk ?: '';
            if ( $last_size === $this->_storage_bytes || substr($out, -1) === "\n" ) {
                $this->_read_index = $saved_index;
                @fseek($this->_storage, $this->_read_index);
                $this->_unlock($lock);
                return $out;
            }
            $this->_callback?->__invoke($this, 1024);
            $last_size = $this->_storage_bytes;
        }
    }


    /**
     * Return up to $count bytes, chars, or lines from the IOBuffer and update
     * the internal read index.
     *
     * @param int           $count
     *
     * @return string
     */
    public function read (int $count = 1): string
    {
        $out = $this->peek($count);
        if ( $count < 0 ) {
            $this->_read_index -= strlen($out);
        }
        else {
            $this->_read_index += strlen($out);
        }
        return $out;
    }


    /**
     * Return the contents of the IOBuffer from its current read position up to
     * and including the next "\n", and update the buffer's read position.
     *
     * You can call read_line() repeatedly to get each line in the buffer.
     *
     * @return string
     */
    public function read_line (): string
    {
        $out = $this->peek_line();
        $this->_read_index += strlen($out);
        return $out;
    }


    /**
     * "Rewind" the internal read index $bytes (or as far as it will go) without
     * returning any data.
     *
     * @param int $bytes
     *
     * @return void
     */
    public function rewind (int $bytes = 1): void
    {
        $new = max(0, $this->_read_index - abs($bytes));
        $lock = $this->_lock();
        if ( @fseek($this->_storage, $new) === 0 ) {
            $this->_read_index = $new;
        }
        $this->_unlock($lock);
    }


    /**
     * Return any unread data in the IOBuffer, and then clear the IOBuffer and
     * reset counters. This is typically used to get the last incomplete bit of
     * data (if any) from the IOBuffer before closing the device attached to it.
     *
     * @return string
     */
    public function flush (): string
    {
        $out = '';
        while ( ($chunk = $this->read(8192)) !== '' ) {
            $out .= $chunk;
        }
        $lock = $this->_lock();
        if ( ! @ftruncate($this->_storage, 0) ) {
            throw new RuntimeException('Could not truncate internal storage');
        }
        if ( ! @fseek($this->_storage, 0) ) {
            throw new RuntimeException('Could not reset the file pointer for internal storage');
        }
        $this->_storage_bytes = 0;
        $this->_read_index = 0;
        $this->_unlock($lock);
        return $out;
    }


    /**
     * Return the current line number and position of the IOBuffer.
     *
     * This returns the line and offset of the last read data. If no data has
     * been read yet, it returns [0, 0]; after the first character, [1, 1];
     * if reading in line mode and the first line is read, [1, <length of line>].
     *
     * @return  array
     */
    public function get_position (): array
    {
        if ( $this->_read_index === 0 ) {
            return ['line' => 0, 'position' => 0];
        }
        $last_read = $this->_read_index - 1;
        $line = 0;
        $line_count = count($this->_newlines);
        while ( $line < $line_count && $this->_newlines[$line] < $last_read ) {
            $line++;
        }
        $position = $line === 0 ? $this->_read_index : $last_read - $this->_newlines[$line - 1];
        return ['line' => ++$line, 'position' => $position];
    }


}
