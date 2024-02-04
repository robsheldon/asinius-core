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

use RuntimeException;

class IOBuffer
{

    //  Read modes.
    const   RAWMODE       = 0b000000010000000000000000;
    const   CHARMODE      = 0b000000100000000000000000;
    const   LINEMODE      = 0b000001000000000000000000;
    const   MODEMASK      = self::RAWMODE | self::CHARMODE | self::LINEMODE;

    //  Other options.
    const   TRACKING      = 0b000010000000000000000000;


    protected         string $_pending         = '';
    protected         mixed  $_cache           = '';
    protected         int    $_cache_position  = 0;
    protected        ?int    $_max_buffer_size = null;
    public    static  int    $MAX_BUFFER_SIZE  = PHP_INT_MAX;
    protected         int    $_flags           = self::RAWMODE;
    protected        ?string $_charset         = null;


    /**
     * Set the maximum buffer size for this instance. This overrides the static
     * IOBuffer::$MAX_BUFFER_SIZE value (whether it's larger or smaller).
     *
     * @param ?int $size
     *
     * @return int
     */
    public function max_buffer_size (int $size = null): int
    {
        if ( $size !== null ) {
            $this->_max_buffer_size = $size;
        }
        return $this->_max_buffer_size ?? static::$MAX_BUFFER_SIZE;
    }


    /**
     * Return a new IOBuffer.
     */
    public function __construct ()
    {
        //  No setup required at this time.
    }


    /**
     * Append some raw data to this IOBuffer.
     *
     * Code that interfaces with an i/o device would use this to append data to
     * their IOBuffer after reading it from the device.
     *
     * Data is immediately converted (as much as possible) into the type expected
     * by the IOBuffer's read mode.
     *
     * @param string $data
     *
     * @return void
     */
    public function append (string $data): void
    {
        //  Process the raw data according to the current read mode option and
        //  append the result to the cache.
        if ( $this->_flags & static::RAWMODE ) {
            $this->_cache .= $data;
            $cache_size = strlen($this->_cache);
        }
        else {
            $this->_pending .= $data;
            if ( $this->_flags & static::CHARMODE ) {
                $chunk = Multibyte::strcut($this->_pending, 0, null, $this->_charset);
                $this->_pending = substr($this->_pending, strlen($chunk));
                $this->_cache   = array_merge($this->_cache, Multibyte::str_split($chunk, 1, $this->_charset));
            }
            else {
                //  The end-of-line delimiter MUST be returned, otherwise
                //  there's no way to make write() and read() idempotetent.
                //  If a caller write()s the contents of a file read() in
                //  line mode, the caller can't know if the file was
                //  terminated in a newline or not.
                //  PREG_SPLIT_DELIM_CAPTURE captures the delimiters, alright,
                //  but it puts them in their own array elements. Sigh.
                $lines = [];
                $mangled_lines = preg_split("/(\r?\n)/", $this->_pending, 0, PREG_SPLIT_DELIM_CAPTURE);
                $n = count($mangled_lines);
                for ( $i = 0; $i < $n; $i += 2 ) {
                    $lines[] = $mangled_lines[$i] . ($mangled_lines[$i + 1] ?? '');
                }
                if ( count($lines) > 1 ) {
                    //  Save the last (possibly incomplete) line in the pending buffer.
                    $this->_pending = array_pop($lines);
                    $this->_cache   = array_merge($this->_cache, $lines);
                }
            }
            $cache_size = count($this->_cache);
        }
        //  Trim the read cache if needed after appending to it.
        $trim = min($this->_cache_position, max(0, $cache_size - ($this->_max_buffer_size ?? static::$MAX_BUFFER_SIZE)));
        if ( $trim > 0 ) {
            $this->_cache = is_array($this->_cache) ? array_slice($this->_cache, $trim) : substr($this->_cache, $trim);
            $this->_cache_position -= $trim;
        }
    }


    /**
     * Retrieve $count bytes, chars, or lines from the IOBuffer without moving
     * the internal read index. i.e., two consecutive peek() operations will
     * return the same data.
     *
     * Callers can (should) provide a callback function $read_callback. This
     * function will be called if peek() needs to return more data than is
     * currently stored in the IOBuffer, and the callback function should try
     * to append() more data to this IOBuffer.
     *
     * $read_callback will be called as $read_callback(IOBUffer $this, int $needed_count)
     * NOTE: $needed_count is _not_ guaranteed to be a number of bytes! If the
     * IOBuffer's read mode is CHARMODE or LINEMODE, $needed_count will be the
     * number of chars or lines needed, respectively. But, this is okay! The
     * callback will be called repeatedly until the peek() request is fulfilled
     * or the callback is not able to append() any more data to the IOBuffer.
     *
     * @param int $count
     * @param callable|null $read_callback
     *
     * @return array|string|null
     */
    public function peek (int $count = 1, ?Callable $read_callback = null): array|string|null
    {
        if ( $count < 0 ) {
            return null;
        }
        $last_cache_size = -1;
        $cache_is_array = is_array($this->_cache);
        while ( true ) {
            //  Estimate how much data is still needed.
            $cache_size = $cache_is_array ? count($this->_cache) : strlen($this->_cache);
            $remaining = $count - $cache_size + $this->_cache_position;
            //  1. Return null if:
            //      a. No data is available and no data was returned by $read_callback.
            //  2. Return some or all of the read cache if:
            //      a. The requested data is already in the cache;
            //      b. Some data was returned by $read_callback but no more is available;
            //      c. Data has been returned by $read_calback and it satisfies the request.
            if ( $last_cache_size ===  $cache_size || $remaining <= 0 ) {
                if ( $remaining === $count ) {
                    //  No more data available (at this time?).
                    //  Signal that peek() failed to retrieve any data.
                    return null;
                }
                //  Return whatever is in the cache.
                if ( $cache_is_array ) {
                    $out = array_slice($this->_cache, $this->_cache_position, min($count, $cache_size - $this->_cache_position));
                    return ( $count === 1 && count($out) > 0 ) ? $out[0] : $out;
                }
                return substr($this->_cache, $this->_cache_position, min($count, $cache_size - $this->_cache_position));
            }
            if ( $read_callback !== null ) {
                //  Ask the caller to try append()ing a $remaining amount of data,
                //  probably through a read() on their i/o device.
                //  $remaining might not be in bytes! But, this will get called
                //  repeatedly until the peek() request is fulfilled, so we're okay.
                $read_callback($this, $remaining);
            }
            $last_cache_size = $cache_size;
        }
    }


    /**
     * Return up to $count bytes, chars, or lines from the IOBuffer and update
     * the internal read index.
     *
     * Callers can (should) provide a callback function $read_callback. This
     * function will be called if read() needs to return more data than is
     * currently stored in the IOBuffer, and the callback function should try
     * to append() more data to this IOBuffer.
     *
     * @param int           $count
     * @param callable|null $read_callback
     *
     * @return array|string|null
     */
    public function read (int $count = 1, ?Callable $read_callback = null): array|string|null
    {
        $out = $this->peek($count, $read_callback);
        if ( $out !== null ) {
            if ( is_string($this->_cache) ) {
                $this->_cache_position += strlen($out);
            }
            else if ( is_string($out) ) {
                $this->_cache_position += strlen($out) > 1 ? 1: 0;
            }
            else {
                $this->_cache_position += count($out);
            }
        }
        return $out;
    }


    /**
     * Return any unread data, _and_ any data left in the internal "pending"
     * buffer, in whatever mode the IOBuffer is currently using, and then clear
     * all buffers and reset counters. This is typically used to get the last
     * incomplete bit of data (if any) in char or line modes before closing the
     * device attached to the IOBuffer.
     *
     * @return string|array
     */
    public function flush (): string|array
    {
        if ( is_string($this->_cache) ) {
            $out = $this->_cache . $this->_pending;
            $this->_cache = '';
        }
        else {
            if ( $this->_pending != '' ) {
                $out = array_merge($this->_cache, [$this->_pending]);
            }
            else {
                $out = $this->_cache;
            }
            $this->_cache = [];
        }
        $this->_pending = '';
        $this->_cache_position = 0;
        return $out;
    }


    /**
     * Change the read mode for this buffer.
     *
     * Applications can change the read mode while reading from an IOBuffer and
     * it will _mostly_ handle it gracefully.
     *
     * Valid modes are:
     *     IOBuffer::RAWMODE     Treat the buffer as a string of bytes
     *     IOBuffer::CHARMODE    Treat the buffer as an array of multibyte characters
     *     IOBuffer::LINEMODE    Treat the buffer as an array of lines
     *
     * Returns the current mode.
     *
     * @param int|null $mode
     *
     * @return int
     */
    public function mode (int $mode = null): int
    {
        $new_mode_flag = null;
        switch ($mode) {
            case null:
                break;
            case static::RAWMODE:
                //  Data is buffered as a string of bytes, and read(1) returns
                //  the next byte.
                if ( ($this->_flags & static::MODEMASK) === static::RAWMODE ) {
                    break;
                }
                $new_mode_flag = static::RAWMODE;
                if ( is_array($this->_cache) ) {
                    $delim = '';
                    if ( ($this->_flags & static::MODEMASK) === static::LINEMODE ) {
                        $delim = "\n";
                    }
                    $this->_cache_position = strlen(implode($delim, array_slice($this->_cache, 0, $this->_cache_position)));
                    $this->_cache = implode($delim, $this->_cache);
                }
                break;
            case static::CHARMODE:
                //  Data is buffered as an array of characters. Multibyte charset
                //  support is implied. read(1) returns the next character.
                if ( ($this->_flags & static::MODEMASK) === static::CHARMODE ) {
                    break;
                }
                $new_mode_flag = static::CHARMODE;
                if ( ($this->_flags & static::MODEMASK) === static::LINEMODE ) {
                    //  Convert to raw, then will be converted to chars.
                    $this->_cache_position = strlen(implode("\n", array_slice($this->_cache, 0, $this->_cache_position)));
                    $this->_cache = implode("\n", $this->_cache);
                    if ( $this->_cache_position > 0 ) {
                        //  A newline has just been inserted at the
                        //  current read position, so the index needs
                        //  to be advanced one.
                        $this->_cache_position++;
                    }
                    $this->_flags |= static::RAWMODE;
                }
                if ( ($this->_flags & static::MODEMASK) === static::RAWMODE ) {
                    $this->_cache_position = Multibyte::strlen(Multibyte::strcut($this->_cache, 0, $this->_cache_position, $this->_charset), $this->_charset);
                    $this->_cache = Multibyte::str_split($this->_cache, 1, $this->_charset);
                }
                break;
            case static::LINEMODE:
                //  Data is buffered as an array of lines separated
                //  by "\n". read(1) returns the next line.
                //  WARNING WARNING WARNING WARNING
                //  This mode switch WILL add line breaks to your data
                //  if your application has read() into the middle of
                //  a line.
                if ( ($this->_flags & static::MODEMASK) === static::LINEMODE ) {
                    break;
                }
                $new_mode_flag = static::LINEMODE;
                if ( ($this->_flags & static::MODEMASK) === static::RAWMODE ) {
                    if ( strlen($this->_cache) === 0 ) {
                        $this->_cache = [];
                        break;
                    }
                    if ( $this->_cache_position === 0 || $this->_cache_position >= strlen($this->_cache) ) {
                        $this->_cache = preg_split("/\r?\n/", $this->_cache);
                        if ( $this->_cache_position > 0 ) {
                            $this->_cache_position = count($this->_cache);
                        }
                    }
                    else {
                        $read_lines = preg_split("/\r?\n/", substr($this->_cache, 0, $this->_cache_position));
                        $this->_cache = array_merge($read_lines, preg_split("/\r?\n/", substr($this->_cache, $this->_cache_position)));
                        $this->_cache_position = count($read_lines);
                    }
                }
                else if ( ($this->_flags & static::MODEMASK) === static::CHARMODE ) {
                    if ( count($this->_cache) === 0 ) {
                        break;
                    }
                    if ( $this->_cache_position === 0 || $this->_cache_position >= count($this->_cache) ) {
                        $this->_cache = preg_split("/\r?\n/", implode('', $this->_cache));
                        if ( $this->_cache_position > 0 ) {
                            $this->_cache_position = count($this->_cache);
                        }
                    }
                    else {
                        $read_lines = preg_split("/\r?\n/", implode('', array_slice($this->_cache, 0, $this->_cache_position)));
                        $this->_cache = array_merge($read_lines, preg_split("/\r?\n/", implode('', array_slice($this->_cache, $this->_cache_position))));
                        $this->_cache_position = count($read_lines);
                    }
                }
                break;
            default:
                throw new RuntimeException(sprintf("\"0b%032b\" is not a valid %s mode", $mode, __CLASS__));
        }
        if ( $new_mode_flag !== null ) {
            $this->_flags &= ~static::MODEMASK;
            $this->_flags |= $new_mode_flag;
        }
        return $this->_flags & static::MODEMASK;
    }
}