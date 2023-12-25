<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Datastream\Resource                                                *
*                                                                              *
*   This class implements the Datastream interface for PHP resource values --  *
*   files, pipes, and network sockets. It is intended to be able to read from  *
*   and write to anything that PHP natively supports with a simpler, more      *
*   reliable, and efficient interface.                                         *
*                                                                              *
*   IMPLEMENTATION NOTES                                                       *
*                                                                              *
*   When reading, a Resource Datastream stores data in two buffer-like         *
*   structures: the read buffer and the read cache. The read buffer is a raw   *
*   store of bytes read from the connection endpoint. The read cache contains  *
*   data processed from the read buffer into whatever format the application   *
*   is expecting. For example, in the case of a file, the application may be   *
*   reading the file line-at-a-time but internally files are read 8 KB at a    *
*   time. The read buffer will store up to the next 8 KB of data in the file,  *
*   but the read cache stores the N lines of the file. The application can     *
*   read() these one at a time or in batches or all at once, and rewind them   *
*   as needed up to the limits it sets for the read cache. The read cache      *
*   stores as much history as it can, purging old reads as new reads are       *
*   requested by the application.                                              *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
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

namespace Asinius\Datastream;

use RuntimeException;
use Asinius\Datastream;
use Asinius\Datastream\Properties as DatastreamProperties;
use Asinius\Functions;
use Asinius\Multibyte;
use Asinius\StrictArray;


/*******************************************************************************
*                                                                              *
*   \Asinius\Datastream\Resource                                               *
*                                                                              *
*******************************************************************************/

class Resource implements Datastream
{

    private const STREAMOPT_RAWMODE     = 0b0001;
    private const STREAMOPT_CHARMODE    = 0b0010;
    private const STREAMOPT_LINEMODE    = 0b0100;
    private const STREAMOPT_MODEMASK    = 0b0111;
    private const STREAMOPT_TRACKING    = 0b1000;

    protected $_connection              = null;
    protected $_type                    = 0;
    protected $_name                    = null;
    protected $_path                    = null;
    protected $_close_when_done         = false;
    protected $_flags                   = 0;
    protected $_state                   = Datastream::STREAM_UNOPENED;
    protected $_functions               = [];
    //  $_read_buffer stores data retrieved from the Datastream's endpoint
    //  that hasn't been consumed by the application yet.
    protected $_read_buffer             = '';
    protected $_read_buffer_size        = 0;
    //  $_read_cache stores data that has been prepared by peek() to be
    //  consumed by the application.
    protected $_read_cache              = null;
    protected $_read_cache_position     = 0;
    protected $_read_cache_max_count    = 0;
    //  $_write_buffer stores data that's being written. This allows for
    //  asymmetric read/write operations, for example from a blocking pipe
    //  to a file (or from a file to a pipe).
    protected $_write_buffer            = '';
    //  $_data_sources is a list of one or more resource types that have
    //  been passed to the write() function for this object. They are cached
    //  here so that they don't get unintentionally closed by __destruct()
    //  when they get converted into a Datastream.
    protected $_data_sources            = null;
    //  Support for different character encodings during read() operations.
    protected $_charset                 = 'ASCII';
    //  The maximum number of bytes requested for each read() operation.
    //  Default is the size of a typical OS memory page.
    protected $_read_chunk_size         = 4096;
    protected $_load                    = Datastream::IO_LOAD_0;
    protected $_sleep                   = Datastream::STREAM_SLEEP_MAX;
    protected $_timeout                 = 5000000;
    //  $_timeout_max is used for varying application timeouts.
    protected $_timeout_max             = 5000000;
    //  Line and position tracking. Positions are 1-indexed.
    protected $_line                    = 0;
    protected $_position                = 0;

    use DatastreamProperties;


    /**
     * Return a stream type according to the value of get_resource_type().
     *
     * @param $resource
     *
     * @return  int
     */
    public static function get_stream_type ($resource): int
    {
        if ( (defined('STDIN') && $resource === STDIN) || (defined('STDOUT') && $resource === STDOUT) || (defined('STDERR') && $resource === STDERR) ) {
            return Datastream::STREAM_PIPE;
        }
        switch (strtolower(@get_resource_type($resource))) {
            case 'stream':
                return Datastream::STREAM_GENERIC;
            case 'socket':
                return Datastream::STREAM_SOCKET;
            //  These are listed here for informational / reference purposes.
            case 'birdstep link':
            case 'birdstep result':
            case 'bzip2':
            case 'cubrid connection':
            case 'persistent cubrid connection':
            case 'cubrid request':
            case 'cubrid lob':
            case 'cubrid lob2':
            case 'curl':
            case 'dba':
            case 'dba persistent':
            case 'dbase':
            case 'dbx_link_object':
            case 'dbx_result_object':
            case 'fbsql link':
            case 'fbsql plink':
            case 'fbsql result':
            case 'fdf':
            case 'ftp':
            case 'gd':
            case 'gd font':
            case 'gd ps encoding':
            case 'gd ps font':
            case 'gmp integer':
            case 'imap':
            case 'ingres':
            case 'ingres persistent':
            case 'interbase blob':
            case 'interbase link':
            case 'interbase link persistent':
            case 'interbase query':
            case 'interbase result':
            case 'interbase transaction':
            case 'ldap link':
            case 'ldap result':
            case 'ldap result entry':
            //  These are not typos:
            case 'mnogosearch agent':
            case 'mnogosearch result':
            case 'msql link':
            case 'msql link persistent':
            case 'msql query':
            case 'mssql link':
            case 'mssql link persistent':
            case 'mssql result':
            case 'mysql link':
            case 'mysql link persistent':
            case 'mysql result':
            case 'oci8 collection':
            case 'oci8 connection':
            case 'oci8 lob':
            case 'oci8 statement':
            case 'odbc link':
            case 'odbc link persistent':
            case 'odbc result':
            case 'openssl key':
            case 'openssl x.509':
            case 'pdf document':
            case 'pdf image':
            case 'pdf object':
            case 'pdf outline':
            case 'pgsql large object':
            case 'pgsql link':
            case 'pgsql link persistent':
            case 'pgsql result':
            case 'pgsql string':
            case 'printer':
            case 'printer brush':
            case 'printer font':
            case 'printer pen':
            case 'pspell':
            case 'pspell config':
            case 'shmop':
            case 'sockets file descriptor set':
            case 'sockets i/o vector':
            case 'swfaction':
            case 'swfbitmap':
            case 'swfbutton':
            case 'swfdisplayitem':
            case 'swffill':
            case 'swffont':
            case 'swfgradient':
            case 'swfmorph':
            case 'swfmovie':
            case 'swfshape':
            case 'swfsprite':
            case 'swftext':
            case 'swftextfield':
            case 'sybase-ct link':
            case 'sybase-ct link persistent':
            case 'sybase-ct result':
            case 'sybase-db link':
            case 'sybase-db link persistent':
            case 'sybase-db result':
            case 'sysvsem':
            case 'sysvshm':
            case 'wddx':
            case 'xml':
            case 'xpath context':
            case 'xpath object':
            case 'zlib':
            case 'zlib.default':
            case 'zlib.inflate':
            default:
                return Datastream::STREAM_UNSUPPORTED;
        }
    }


    /**
     * This function allows non-blocking streams to behave like blocking streams
     * with a timeout. It ALMOST isn't necessary here, except for STDIN, which
     * will block by default.
     *
     * Note: some of the approaches here won't work correctly in Windows
     * environments. See also https://bugs.php.net/bug.php?id=34972
     * 
     * @param   int     $operation
     *
     * @internal
     *
     * @return  mixed
     *
     */
    protected function _poll (int $operation)
    {
        //  Run $function on an interval determined by current i/o load, for
        //  up to $this->_timeout microseconds, then return all of the function
        //  results as an array (not including any false values).
        //  Fast network load calculation:
        //      on each interval, shift load value left by 1 bit (<<)
        //      if non-false result, shift load value right by 1 bit (>>)
        //  This gives a rudimentary load meter that looks like:
        //      1111111110000000000000000000000
        //  PHP is a twos-complement binary system, so start with (-PHP_INT_MAX>>1<<1).
        //  This shifts bit #1 off the right, and then shifts a zero back on,
        //  to give e.g. 10000000000000000000.
        //  PHP will then shift bits on from the left and off from the right.
        //  Get the number of bits with strlen(decbin($load)).
        //  If the network load is above a specific value, then the sleep time
        //  steadily decrements. If the network load is below another value,
        //  then the sleep time steadily increments. These values can be checked
        //  using bitmasks defined at startup.
        //  Sleep time would be between 0b11111000000000000000 and 0b00000000000000011111
        //  (1.01s and 31usecs).
        $function = $this->_functions[$operation];
        //  List of result values.
        $results = [];
        list($start_secs, $start_usecs) = array_values(gettimeofday(false));
        while ( true ) {
            //  Start the timer. gettimeofday(false) is used here because
            //  microtime() and gettimeofday(true) don't return enough precision
            //  in the microseconds part of the timestamp.
            $now = gettimeofday(false);
            $elapsed = ($now['sec'] - $start_secs) * 1000000 + $now['usec'] - $start_usecs;
            $result = $function();
            //  Continue polling until one of the following conditions is met:
            if ( $result ) {
                //  Successful function call.
                //  Unbelievably, that test works correctly for all function results.
                $results[] = $result;
                $this->_load = $this->_load >> 1;
                if ( $this->_load & Datastream::IO_LOAD_HIGH ) {
                    //  I/O load is high right now, decrease sleep time.
                    $this->_sleep = max($this->_sleep>>1, Datastream::STREAM_SLEEP_MIN);
                    //  Also decrease the application's timeout period if it has
                    //  set up variable timeouts.
                    if ( $this->_state & Datastream::STREAM_VARY_TIMEOUT ) {
                        $this->_timeout = $this->_timeout >> 1;
                    }
                }
                if ( $this->_timeout < 0 || $elapsed >= $this->_timeout ) {
                    //  Timeout period has elapsed, return the current set of
                    //  results to the upstream handler.
                    return $results;
                }
            }
            else {
                $this->_load = $this->_load << 1 | Datastream::IO_LOAD_0;
                if ( ! ($this->_load & Datastream::IO_LOAD_LOW) ) {
                    //  I/O load is low, increase sleep time.
                    $this->_sleep = min($this->_sleep<<1, Datastream::STREAM_SLEEP_MAX);
                    //  Also increase the application's timeout period if it
                    //  has set up variable timeouts.
                    if ( ($this->_state & Datastream::STREAM_VARY_TIMEOUT) && $this->_timeout < $this->_timeout_max ) {
                        $this->_timeout = min($this->_timeout << 1 | 1, $this->_timeout_max);
                    }
                }
                if ( count($results) > 0 || ($this->_timeout >= 0 && $elapsed >= $this->_timeout) ) {
                    //  Earlier function calls have completed successfully and
                    //  current one returned nothing, so return data to the
                    //  application. Or, the timeout period has expired and no
                    //  function calls succeeded.
                    return $results;
                }
                else
                {
                    //  Application has set a timeout value that hasn't been
                    //  reached yet. Sleep a bit so the processor doesn't get
                    //  thrashed too hard. A value of 1000 microseconds (1 ms)
                    //  amounts to about 10% of a 2nd gen i3 core.
                    usleep($this->_sleep);
                }
            }
        }
    }


    /**
     * Read raw data from the current connection endpoint and append it to the
     * internal read buffer, returning the number of bytes read.
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  int
     */
    protected function _readf () : int
    {
        switch ($this->_type) {
            case Datastream::STREAM_FILE:
                if ( $this->_state & Datastream::STREAM_WRITABLE ) {
                    if ( ! $this->_state & Datastream::STREAM_READABLE ) {
                        throw new RuntimeException("Can't read() or peek() from this stream because it is write-only", EACCESS);
                    }
                    //  The first read() operation for a file makes the file
                    //  read-only for the remainder of the stream.
                    $this->_state &= ~Datastream::STREAM_WRITABLE;
                }
                if ( @feof($this->_connection) ) {
                    //  It's tempting to cache this or close the Datastream at
                    //  this point, but it's possible for an application to be
                    //  reading a file that is being appended by another process,
                    //  so let's re-check for eof on each call to be safe.
                    return 0;
                }
                $chunk = @fread($this->_connection, $this->_read_chunk_size);
                $this->_read_buffer .= $chunk;
                return strlen($chunk);
            case Datastream::STREAM_PIPE:
                //  This needs special handling because fread() on a pipe might block
                //  and applications usually don't want that.
            case Datastream::STREAM_SOCKET:
                //  This requires the _poll() function above.
            case Datastream::STREAM_GENERIC:
                //  Need to figure out what to do here.
            default:
                throw new RuntimeException(sprintf('Oops: read() and peek() are not supported on this Resource->_type (%s)', $this->_type));
        }
    }


    /**
     * Write raw data from the internal write buffer, returning the number of
     * bytes written.
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  int
     */
    protected function _writef () : int
    {
        if ( $this->_type === Datastream::STREAM_FILE || $this->_name === 'STDOUT' || $this->_name === 'STDERR' ) {
            if ( $this->_state & Datastream::STREAM_READABLE ) {
                if ( ! $this->_state & Datastream::STREAM_WRITABLE ) {
                    throw new RuntimeException("Can't write() to this stream because it is read-only", EACCESS);
                }
                //  The first write() operation for a file makes the file
                //  write-only for the remainder of the stream.
                $this->_state &= ~Datastream::STREAM_READABLE;
                if ( $this->_type === Datastream::STREAM_FILE ) {
                    //  Also, move the file pointer to the end of the file
                    //  by default.
                    fseek($this->_connection, 0, SEEK_END);
                }
            }
            if ( strlen($this->_write_buffer) < 1 ) {
                return 0;
            }
            if ( ($bytes = @fwrite($this->_connection, $this->_write_buffer)) > 0 ) {
                $this->_write_buffer = (string) substr($this->_write_buffer, $bytes);
            }
            return $bytes;
        }
        throw new RuntimeException('_writef() has not been implemented for this type of stream', ENOSYS);
    }


    /**
     * Return true if EOF, on supported streams. On non-supported streams,
     * return false.
     *
     * @return  boolean
     */
    protected function _eof () : bool
    {
        if ( $this->_type === Datastream::STREAM_FILE && $this->_state & Datastream::STREAM_READABLE ) {
            return feof($this->_connection);
        }
        return false;
    }


    /**
     * Return a new \Asinius\Resource.
     *
     * @param   mixed       $resource
     *
     * @throws  RuntimeException
     */
    public function __construct ($resource)
    {
        //  Set an error condition now so that no further operations will be
        //  successfully executed if the constructor doesn't complete successfully.
        $this->_state |= Datastream::STREAM_ERROR;
        //  Start the Datastream in raw mode.
        $this->_flags |= static::STREAMOPT_RAWMODE;
        $this->_read_buffer = '';
        $this->_read_cache  = '';
        $this->_data_sources = new StrictArray();
        if ( is_string($resource) ) {
            $this->_name = $resource;
            if ( ($resource === 'STDIN' || $resource === 'STDOUT' || $resource === 'STDERR') && ! defined($resource) ) {
                //  This pipe needs to be opened, if possible, before continuing.
                switch ($resource) {
                    case 'STDIN':
                        $pipe = fopen('php://stdin', 'r');
                        if ( $pipe === false ) {
                            throw new RuntimeException("Can't open $resource for reading in this environment");
                        }
                        break;
                    case 'STDOUT':
                    case 'STDERR':
                        $pipe = fopen(sprintf('php://%s', strtolower($resource)), 'a');
                        if ( $pipe === false ) {
                            throw new RuntimeException("Can't open $resource for writing in this environment");
                        }
                       break;
                    default:
                        throw new RuntimeException('Reached impossible code path');
                }
                //  Create the constant to be used everywhere else.
                define($resource, $pipe);
            }
            switch ($resource) {
                case 'STDIN':
                case 'STDOUT':
                case 'STDERR':
                    $pipes = ['STDIN' => STDIN, 'STDOUT' => STDOUT, 'STDERR' => STDERR];
                    $this->_connection = $pipes[$resource];
                    $this->_type = Datastream::STREAM_PIPE;
                    break;
                default:
                    //  This may be a path to a file. There are a few rules that
                    //  must be followed to prevent unsafe access to an application's
                    //  own directory:
                    $this->_type = Datastream::STREAM_FILE;
                    $this->_state |= (Datastream::STREAM_READABLE | Datastream::STREAM_WRITABLE);
                    $this->_path = @realpath($resource);
                    if ( $this->_path === false ) {
                        //  1. If the file doesn't already exist, then the parent
                        //  directory must exist and must be writable.
                        $parent_dir = @realpath(dirname($resource));
                        if ( $parent_dir === false ) {
                            throw new RuntimeException("File at $resource doesn't exist and it can't be created because the parent directory doesn't exist", ENOENT);
                        }
                        if ( ! is_writable($parent_dir) ) {
                            throw new RuntimeException("File at $resource doesn't exist and it can't be created because the parent directory isn't writable", EACCESS);
                        }
                        //  Parent directory exists and is writable, continue.
                        $this->_path = implode(DIRECTORY_SEPARATOR, [$parent_dir, basename($resource)]);
                        //  This file is not readable, however.
                        $this->_state &= ~Datastream::STREAM_READABLE;
                    }
                    if ( strpos($this->_path, getcwd()) === 0 && $resource !== $this->_path ) {
                        //  2. Paths can not indirectly reference a location in
                        //  the application's current directory. It's not safe
                        //  for a library to implicitly enable access to files
                        //  under the current directory because there's no way
                        //  to know if the input is from a trusted or untrusted
                        //  source. If applications want to use this class
                        //  to access files in their own directory, either use
                        //  an absolute path or fopen() the path first and pass
                        //  the resource handle instead.
                        throw new RuntimeException("The application tried to access a file in its own directory. Wait, that's illegal", EACCESS);
                    }
            }
        }
        else if ( @is_resource($resource) ) {
            $this->_connection = $resource;
            $this->_type = static::get_stream_type($resource);
            switch ($this->_type) {
                case Datastream::STREAM_PIPE:
                    if ( $resource === STDIN ) {
                        $this->_name = 'STDIN';
                    }
                    else if ( $resource === STDOUT ) {
                        $this->_name = 'STDOUT';
                    }
                    else if ( $resource === STDERR ) {
                        $this->_name = 'STDERR';
                    }
                    break;
                case Datastream::STREAM_GENERIC:
                    $metadata = stream_get_meta_data($resource);
                    switch ($metadata['wrapper_type']) {
                        case 'plainfile':
                            $this->_name = $metadata['uri'];
                            $this->_type = Datastream::STREAM_FILE;
                            switch ($metadata['mode']) {
                                case 'r':
                                    $this->_state |= Datastream::STREAM_READABLE;
                                    $this->_state &= ~Datastream::STREAM_WRITABLE;
                                    break;
                            }
                            break;
                    }
                    break;
                case Datastream::STREAM_UNSUPPORTED:
                    throw new RuntimeException("Can't create a " . __CLASS__ . ' from this resource type: ' . Functions::to_str(@get_resource_type($resource)));
            }
        }
        else {
            throw new RuntimeException("Can't create a " . __CLASS__ . ' from this: ' . Functions::to_str($resource), EINVAL);
        }
        //  Set the unopened flag and unset the error flag.
        $this->_state |= Datastream::STREAM_UNOPENED;
        $this->_state &= ~Datastream::STREAM_ERROR;
        if ( $this->_type === Datastream::STREAM_FILE ) {
            //  Files default to line mode.
            $this->set(['mode' => 'line']);
        }
        else if ( $this->_name === 'STDIN' ) {
            //  STDIN should be made non-blocking by default.
            @stream_set_blocking($this->_connection, 0);
            //  STDIN is also read-only.
            $this->_state &= ~Datastream::STREAM_WRITABLE;
        }
        else if ( $this->_name === 'STDOUT' || $this->_name === 'STDERR' ) {
            //  These pipes are write-only.
            $this->_state &= ~Datastream::STREAM_READABLE;
        }
    }


    /**
     * If this object open()ed the resource, then close it when the object is
     * destroyed.
     *
     * @return  void
     */
    public function __destruct ()
    {
        if ( $this->_close_when_done ) {
            $this->close();
        }
    }


    /**
     * If the datastream didn't receive an already-opened resource in its
     * constructor, then it can be opened here.
     *
     * This function will be called by read() and write() if the Datastream
     * hasn't been opened yet, but applications can explicitly call this as
     * needed.
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function open ()
    {
        if ( $this->_state & Datastream::STREAM_CLOSED ) {
            throw new RuntimeException('open(): Datastream has already been closed', ENOTCONN);
        }
        else if ( $this->_state & Datastream::STREAM_ERROR ) {
            throw new RuntimeException("open(): Datastream can't be opened due to a previous error", EHALTED);
        }
        else if ( ! ($this->_state & Datastream::STREAM_UNOPENED) ) {
            return;
        }
        if ( $this->_connection === null && strlen($this->_path) > 0 ) {
            //  The constructor signals that the application wanted to open a
            //  file by leaving _connection null and storing a path.
            //  Open a file resource at the saved path.
            //  This path has already been validated in__construct(), all that
            //  remains now is to open it in the best way available.
            if ( ! @file_exists($this->_path) ) {
                if ( ($this->_connection = @fopen($this->_path, 'w')) === false ) {
                    $this->_state |= Datastream::STREAM_ERROR;
                    throw new RuntimeException('Failed to open file at ' . $this->_path . ' for writing', EACCESS);
                }
                //  This path can only be writable.
                $this->_state &= ~Datastream::STREAM_READABLE;
            }
            else if ( ! @is_writable($this->_path) ) {
                if ( ($this->_connection = @fopen($this->_path, 'r')) === false ) {
                    $this->_state |= Datastream::STREAM_ERROR;
                    throw new RuntimeException('Failed to open file at ' . $this->_path . ' for reading', EACCESS);
                }
                //  This path can only be readable.
                $this->_state &= ~Datastream::STREAM_WRITABLE;
            }
            else {
                //  Open the file for reading and writing; the first read()
                //  operation will make the file unwritable, while the first
                //  write() operation will make the file unreadable. If write()
                //  gets called first, it will set the file pointer to the end
                //  of the file. The application can override this by opening
                //  the file itself and passing the resource handle to the
                //  constructor instead.
                if ( ($this->_connection = @fopen($this->_path, 'r+')) === false ) {
                    $this->_state |= Datastream::STREAM_ERROR;
                    throw new RuntimeException('Failed to open file at ' . $this->_path, EACCESS);
                }
            }
            $this->_close_when_done = true;
        }
        if ( ! @is_resource($this->_connection) ) {
            //  This code path should be impossible, but something someday will
            //  land here I'm sure.
            $this->_state |= Datastream::STREAM_ERROR;
            throw new RuntimeException('Stream does not exist', ENOTCONN);
        }
        //  Install the mid-level stream operation functions that are appropriate
        //  for this type of stream.
        $this->_state &= ~Datastream::STREAM_UNOPENED;
        $this->_state |= Datastream::STREAM_CONNECTED;
    }


    /**
     * Return true if this Datastream can currently support either read() or
     * write() operations (or maybe both), or false if it's closed or stuck
     * with an error.
     *
     * @return  boolean
     */
    public function ready () : bool
    {
        //  It's okay to consider STREAM_UNOPENED to be "ready" here; the next
        //  read() or write() call will implicitly open the datastream.
        return ( (! ($this->_state & Datastream::STREAM_ERROR)) && (($this->_state & Datastream::STREAM_CONNECTED) || ($this->_state & Datastream::STREAM_UNOPENED)) );
    }


    /**
     * read() this Datastream until some matching content is found, and then
     * queue that content up for the next read().
     *
     * TODO
     *
     * @param   mixed       $query
     */
    public function search ($query)
    {
    }


    /**
     * Return true if there is nothing more to read(), false otherwise.
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public function empty (): bool
    {
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new RuntimeException('eof(): stream is not connected', ENOTCONN);
        }
        return is_null($this->peek());
    }


    /**
     * Return the name/label/description for this Datastream.
     *
     * If the Datastream is a file, returns the full path to the file (as set
     * by an earlier call to realpath() in the constructor).
     *
     * @return  string
     */
    public function name (): string
    {
        if ( $this->_type === Datastream::STREAM_FILE ) {
            return $this->_path;
        }
        return $this->_name;
    }


    /**
     * Return the stream type for this Datastream, as defined under "Stream types"
     * in the Datastream interface.
     *
     * @return  int
     */
    public function type (): int
    {
        return $this->_type;
    }


    /**
     * Returns true if this Datastream is readable, false otherwise.
     *
     * @return  boolean
     */
    public function is_readable (): bool
    {
        return ($this->_state & Datastream::STREAM_READABLE) !== 0;
    }


    /**
     * Return the next bytes, page, row, line, element, etc. Return null when
     * there is no more data to return.
     *
     * In line mode, the end-of-line delimiter (\r\n or \n) is included at the
     * end of each line.
     *
     * @param   int         $count
     *
     * @throws  RuntimeException
     *
     * @return  mixed
     */
    public function read (int $count = 1)
    {
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new RuntimeException('read(): stream is not connected', ENOTCONN);
        }
        //  Load the requested data into the read cache if it's not already
        //  available there.
        $out = $this->peek($count);
        if ( $out === null ) {
            //  No further action needed.
            return null;
        }
        //  Advance the internal read cache index.
        if ( is_array($this->_read_cache) ) {
            if ( $count === 1 && ! is_array($out) ) {
                $out = [$out];
            }
            $i = count($out);
        }
        else if ( is_string($out) ) {
            $i = strlen($out);
        }
        else {
            throw new RuntimeException("Internal logical error in read(): type mismatch between _read_cache and peek()", EUNDEF);
        }
        $this->_read_cache_position += $i;
        //  If position tracking is enabled, do the expensive counting now.
        if ( $i > 0 && $this->_flags & (static::STREAMOPT_TRACKING | static::STREAMOPT_CHARMODE) ) {
            if ( $this->_line === 0 ) {
                $this->_line = 1;
            }
            $n = 0;
            while ( $i-- ) {
                if ( $out[$i] === "\n" ) {
                    $this->_position = $n;
                    $this->_line++;
                    $n = 0;
                    while ( $i ) {
                        if ( $out[--$i] === "\n" ) {
                            $this->_line++;
                        }
                    }
                    break;
                }
                $n++;
            }
            $this->_position += $n;
        }
        //  Return the first element of $out if the read count is 1.
        //  I'm not sure about this decision. On the one hand, I don't
        //  like returning a string sometimes and an array sometimes.
        //  On the other, I hate constantly dereferencing ->read()[0]
        //  in application code (and checking to make sure that read()
        //  has returned an array with at least one element first).
        return ( $count === 1 && is_array($out) && count($out) > 0 ) ? $out[0] : $out;
    }


    /**
     * Similar to read(), but the internal data buffer is not emptied. This
     * allows the application to "preview" the next chunk of data in the stream.
     *
     * @param   int         $count
     *
     * @return  mixed
     */
    public function peek (int $count = 1)
    {
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new RuntimeException('peek(): stream is not connected', ENOTCONN);
        }
        if ( $count < 0 ) {
            return null;
        }
        $last_read_count = -1;
        $cache_is_array = is_array($this->_read_cache);
        while ( true ) {
            //  Estimate how much data is still needed.
            $cache_size = $cache_is_array ? count($this->_read_cache) : strlen($this->_read_cache);
            $remaining = $count - $cache_size + $this->_read_cache_position;
            //  1. Return null if:
            //      a. No data was read and no data is available in the connection.
            //  2. Return some or all of the read cache if:
            //      a. The requested data is already in the cache;
            //      b. Some data was read from the connection but no more is available;
            //      c. Data has been read from the connection and it satisfies the request.
            if ( $last_read_count === 0 || $remaining <= 0 ) {
                //  No more data available (at this time?).
                if ( $remaining === $count ) {
                    //  Signal that peek() failed to retrieve any data.
                    return null;
                }
                //  Return whatever is in the cache.
                if ( $cache_is_array ) {
                    $out = array_slice($this->_read_cache, $this->_read_cache_position, min($count, $cache_size - $this->_read_cache_position));
                    return ( $count === 1 && count($out) > 0 ) ? $out[0] : $out;
                }
                return substr($this->_read_cache, $this->_read_cache_position, min($count, $cache_size - $this->_read_cache_position));
            }
            //  Trim the read cache if needed before appending to it.
            $trim = min($this->_read_cache_position, max(0, $cache_size + $count - $this->_read_cache_max_count));
            if ( $trim > 0 ) {
                $this->_read_cache = $cache_is_array ? array_slice($this->_read_cache, $trim) : substr($this->_read_cache, $trim);
                $this->_read_cache_position -= $trim;
            }
            //  Process the data waiting in the read buffer according to the current
            //  stream mode option and append the result to the read cache. Fill the
            //  read buffer as necessary until the read cache is full or there is no
            //  more data available.
            switch ($this->_flags & static::STREAMOPT_MODEMASK) {
                case static::STREAMOPT_RAWMODE:
                    if ( strlen($this->_read_buffer) < $remaining ) {
                        //  Read the next chunk in from the Datastream's connection.
                        $last_read_count = $this->_readf();
                    }
                    $chunk = substr($this->_read_buffer, 0, $remaining);
                    $this->_read_buffer = substr($this->_read_buffer, strlen($chunk));
                    $this->_read_cache .= $chunk;
                    break;
                case static::STREAMOPT_CHARMODE:
                    if ( strlen($this->_read_buffer) < ($remaining * 1.5) ) {
                        $last_read_count = $this->_readf();
                    }
                    $chunk = Multibyte::strcut($this->_read_buffer, 0, (int) ($remaining * 1.5), $this->_charset);
                    $this->_read_buffer = substr($this->_read_buffer, strlen($chunk));
                    $this->_read_cache = array_merge($this->_read_cache, Multibyte::str_split($chunk, 1, $this->_charset));
                    break;
                case static::STREAMOPT_LINEMODE:
                    $lines = 0;
                    while ( ($lines <= 1 || strlen($this->_read_buffer) < ($remaining * 80)) && $last_read_count != 0 ) {
                        $last_read_count = $this->_readf();
                        //  The end-of-line delimiter MUST be returned, otherwise
                        //  there's no way to make write() and read() idempotetent.
                        //  If a caller write()s the contents of a file read() in
                        //  line mode, the caller can't know if the file was
                        //  terminated in a newline or not.
                        $lines = preg_split("/(\r?\n)/", $this->_read_buffer, 0, PREG_SPLIT_DELIM_CAPTURE);
                        if ( ($n = count($lines)) > 1 ) {
                            //  Save the last (possibly incomplete) line in the read buffer.
                            $this->_read_buffer = array_pop($lines);
                            $this->_read_cache = array_merge($this->_read_cache, $lines);
                        }
                        if ( $this->_read_buffer !== '' && $this->_eof() ) {
                            //  Soak up the last line of the file.
                            $this->_read_cache[] = $this->_read_buffer;
                            $this->_read_buffer = '';
                        }
                    }
                    break;
                default:
                    throw new RuntimeException(sprintf('Oops: Resource->_flags has an invalid mode (%s)', $this->_flags & static::STREAMOPT_MODEMASK));
            }
        }
    }


    /**
     * Datastreams should buffer content as much as possible and allow application
     * components to "rewind" the buffer some number of lines, bytes, rows, or
     * other units of data when necessary.
     */
    public function rewind ()
    {
    }


    /**
     * Write data to the current resource.
     *
     * @param   mixed       $data
     *
     * @return  void
     */
    public function write ($data)
    {
        //  A shortcut for a common use case.
        if ( $this->_name === 'STDOUT' && is_a($data, 'Asinius\Datastream\Resource') && $data->type() === Datastream::STREAM_FILE && $data->is_readable() ) {
            readfile($data->name());
            //  IMPORTANT: Callers should call flush() immediately after this.
            //  It can't be called here because flush() may interfere with
            //  output buffering.
        }
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new RuntimeException('write(): stream is not connected', ENOTCONN);
        }
        switch (true) {
            case (is_string($data)):
                $this->_write_buffer .= $data;
                $this->_writef();
                break;
            case (@is_resource($data)):
                if ( ! isset($this->_data_sources[$data]) ) {
                    $this->_data_sources[$data] = new Resource($data);
                }
                $data = $this->_data_sources[$data];
            case (is_a($data, 'Asinius\Datastream\Resource')):
                //  TODO: It would be cool to take advantage of PHP 8.1's new
                //  Fibers feature here and not block the application while the
                //  i/o is completed.
                while ( ($chunk = $data->read()) !== null ) {
                    //  I'm choosing to recurse here to prevent sponging up
                    //  enormous amounts of data from an input source only to
                    //  crash the runtime before any of it gets written.
                    if ( ! is_array($chunk) ) {
                        $chunk = [$chunk];
                    }
                    if ( count($chunk) === 0 ) {
                        break;
                    }
                    foreach ($chunk as $part) {
                        $this->write($part);
                    }
                }
                break;
            default:
                throw new RuntimeException('write() is not supported for this type of data', EINVAL);
        }
    }


    /**
     * Set one or more options on this stream.
     *
     * @param   array   $options
     *
     * @return  void
     */
    public function set (array $options)
    {
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'mode':
                    switch ($value) {
                        case 'raw':
                            //  Data is buffered as a string of bytes, and read(1)
                            //  returns the next byte.
                            if ( $this->_flags & static::STREAMOPT_RAWMODE ) {
                                break(2);
                            }
                            $new_mode_flag = static::STREAMOPT_RAWMODE;
                            if ( is_array($this->_read_cache) ) {
                                $delim = '';
                                if ( $this->_flags & static::STREAMOPT_LINEMODE ) {
                                    $delim = "\n";
                                }
                                $this->_read_cache_position = strlen(implode($delim, array_slice($this->_read_cache, 0, $this->_read_cache_position)));
                                $this->_read_cache = implode($delim, $this->_read_cache);
                            }
                            break;
                        case 'char':
                            //  Data is buffered as an array of characters.
                            //  Multibyte charset support is implied.
                            //  read(1) returns the next character.
                            if ( $this->_flags & static::STREAMOPT_CHARMODE ) {
                                break(2);
                            }
                            $new_mode_flag = static::STREAMOPT_CHARMODE;
                            if ( $this->_flags & static::STREAMOPT_LINEMODE ) {
                                //  Convert to raw, then will be converted to chars.
                                $this->_read_cache_position = strlen(implode("\n", array_slice($this->_read_cache, 0, $this->_read_cache_position)));
                                $this->_read_cache = implode("\n", $this->_read_cache);
                                if ( $this->_read_cache_position > 0 ) {
                                    //  A newline has just been inserted at the
                                    //  current read position, so the index needs
                                    //  to be advanced one.
                                    $this->_read_cache_position++;
                                }
                                $this->_flags |= static::STREAMOPT_RAWMODE;
                            }
                            if ( $this->_flags & static::STREAMOPT_RAWMODE ) {
                                $this->_read_cache_position = Multibyte::strlen(Multibyte::strcut($this->_read_cache, 0, $this->_read_cache_position, $this->_charset), $this->_charset);
                                $this->_read_cache = Multibyte::str_split($this->_read_cache, 1, $this->_charset);
                            }
                            break;
                        case 'line':
                            //  Data is buffered as an array of lines separated
                            //  by "\n". read(1) returns the next line.
                            //  WARNING WARNING WARNING WARNING
                            //  This mode switch WILL add line breaks to your data
                            //  if your application has read() into the middle of
                            //  a line.
                            if ( $this->_flags & static::STREAMOPT_LINEMODE ) {
                                break(2);
                            }
                            $new_mode_flag = static::STREAMOPT_LINEMODE;
                            if ( $this->_flags & static::STREAMOPT_RAWMODE ) {
                                if ( strlen($this->_read_cache) === 0 ) {
                                    $this->_read_cache = [];
                                    break;
                                }
                                if ( $this->_read_cache_position === 0 || $this->_read_cache_position >= strlen($this->_read_cache) ) {
                                    $this->_read_cache = preg_split("/\r?\n/", $this->_read_cache);
                                    if ( $this->_read_cache_position > 0 ) {
                                        $this->_read_cache_position = count($this->_read_cache);
                                    }
                                }
                                else {
                                    $read_lines = preg_split("/\r?\n/", substr($this->_read_cache, 0, $this->_read_cache_position));
                                    $this->_read_cache = array_merge($read_lines, preg_split("/\r?\n/", substr($this->_read_cache, $this->_read_cache_position)));
                                    $this->_read_cache_position = count($read_lines);
                                }
                            }
                            else if ( $this->_flags & static::STREAMOPT_CHARMODE ) {
                                if ( count($this->_read_cache) === 0 ) {
                                    break;
                                }
                                if ( $this->_read_cache_position === 0 || $this->_read_cache_position >= count($this->_read_cache) ) {
                                    $this->_read_cache = preg_split("/\r?\n/", implode('', $this->_read_cache));
                                    if ( $this->_read_cache_position > 0 ) {
                                        $this->_read_cache_position = count($this->_read_cache);
                                    }
                                }
                                else {
                                    $read_lines = preg_split("/\r?\n/", implode('', array_slice($this->_read_cache, 0, $this->_read_cache_position)));
                                    $this->_read_cache = array_merge($read_lines, preg_split("/\r?\n/", implode('', array_slice($this->_read_cache, $this->_read_cache_position))));
                                    $this->_read_cache_position = count($read_lines);
                                }
                            }
                            break;
                        default:
                            throw new RuntimeException("\"$value\" is not a valid value for a stream mode option");
                    }
                    $this->_flags &= ~static::STREAMOPT_MODEMASK;
                    $this->_flags |= $new_mode_flag;
                    break;
                case 'read-chunk-size':
                    if ( ! is_int($value) || $value < 1 ) {
                        throw new RuntimeException("\"$value\" is not a valid value for a Resource's $option", EINVAL);
                    }
                    $this->_read_chunk_size = $value;
                    break;
                case 'read-cache-count':
                    //  The size limit of the internal read cache.
                    //  0 makes it unlimited.
                    //  This is a "soft" limit; if the application attempts
                    //  to read more than is allowed by read-cache-size,
                    //  the cache will hold that much data until the next read.
                    if ( ! is_int($value) || $value < 0 ) {
                        throw new RuntimeException("\"$value\" is not a valid value for a Resource's $option", EINVAL);
                    }
                    $this->_read_cache_max_count = $value;
                    break;
                case 'charset':
                    if ( ! Multibyte::supported_encoding($value) ) {
                        throw new RuntimeException("\"$value\" is not a supported encoding in this runtime environment", EINVAL);
                    }
                    if ( empty($value) ) {
                        throw new RuntimeException("WTF?");
                    }
                    $this->_charset = $value;
                    break;
                case 'tracking':
                    //  Internally track the line and character offset of read()
                    //  operations and make them available through the position()
                    //  function. Tracking only works on "char" mode streams.
                    if ( $value !== true && $value !== false ) {
                        throw new RuntimeException("A true or false boolean value is required for the \"$option\" option", EINVAL);
                    }
                    $this->_flags &= ~static::STREAMOPT_TRACKING;
                    if ( $value ) {
                        $this->_flags |= static::STREAMOPT_TRACKING;
                    }
                    break;
                default:
                    throw new RuntimeException("Unrecognized option: $option", EINVAL);
            }
        }
    }


    /**
     * Return the current mode for the datastream.
     *
     * @throws  RuntimeException
     *
     * @return  string
     */
    public function get_mode () : string {
        switch ($this->_flags & static::STREAMOPT_MODEMASK) {
            case static::STREAMOPT_RAWMODE:
                return 'raw';
            case static::STREAMOPT_CHARMODE:
                return 'char';
            case static::STREAMOPT_LINEMODE:
                return 'line';
            default:
                throw new RuntimeException(sprintf('Oops: Resource->_flags has an invalid mode (%s)', $this->_flags & static::STREAMOPT_MODEMASK));
        }
    }


    /**
     * Return the current line number and position of the datastream if line
     * tracking has been enabled.
     *
     * @return  array
     */
    public function position () : array
    {
        return ['line' => $this->_line, 'position' => $this->_position];
    }


    /**
     * Close the resource associated with this datastream. Any further operations
     * on it will throw an exception.
     *
     * @return  void
     */
    public function close ()
    {
        if ( ($this->_state & Datastream::STREAM_UNOPENED) || ($this->_state & Datastream::STREAM_CLOSED) ) {
            return;
        }
        //  Free up some memory.
        $this->_flags               = 0;
        $this->_read_buffer         = '';
        $this->_read_buffer_size    = 0;
        $this->_read_cache          = null;
        $this->_read_cache_position = 0;
        $this->_state              &= ~Datastream::STREAM_CONNECTED;
        $this->_state              |= Datastream::STREAM_CLOSED;
        if ( $this->_name === 'STDOUT' || $this->_name === 'STDERR' || $this->_name === 'STDIN' ) {
            return;
        }
        if ( $this->_type === Datastream::STREAM_FILE ) {
            @fclose($this->_connection);
        }
    }

}
