<?php

/*******************************************************************************
*                                                                              *
*   Asinius\ResourceDatastream                                                 *
*                                                                              *
*   This class implements the Datastream interface for PHP resource values --  *
*   files, pipes, and so on.
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2020 Rob Sheldon <rob@rescue.dev>                            *
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


/*******************************************************************************
*                                                                              *
*   \Asinius\ResourceDatastream                                                *
*                                                                              *
*******************************************************************************/

class ResourceDatastream implements Datastream
{

    //  Wrapper functions.
    private const STREAM_TIMEOUTF   = 0;
    private const STREAM_ACCEPTF    = 1;
    private const STREAM_READF      = 2;
    private const STREAM_EOFF       = 3;
    private const STREAM_WRITEF     = 4;
    private const STREAM_CLOSEF     = 5;

    private const STREAMOPT_LINEMODE    = 1;
    private const STREAMOPT_CHARMODE    = 2;
    private const STREAMOPT_COUNTLINES  = 4;

    protected $_connection          = null;
    protected $_type                = 0;
    protected $_name                = null;
    protected $_path                = null;
    protected $_close_when_done     = false;
    protected $_flags               = 0;
    protected $_state               = Datastream::STREAM_UNOPENED;
    protected $_functions           = [];
    protected $_read_buffer         = '';
    protected $_write_buffer        = '';
    protected $_load                = Datastream::IO_LOAD_0;
    protected $_sleep               = Datastream::STREAM_SLEEP_MAX;
    protected $_timeout             = 5000000;
    //  $_timeout_max is used for varying application timeouts.
    protected $_timeout_max         = 5000000;
    //  Line and position tracking. Positions are 1-indexed.
    protected $_line                = 0;
    protected $_position            = 0;

    use DatastreamProperties;


    /**
     * Return a stream type according to the value of get_resource_type().
     *
     * @return  int
     */
    public static function get_stream_type ($resource)
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
     * Install the mid-level wrapper functions for operations like read(),
     * write(), and close() for this type of Datastream. This approach
     * streamlines the code a little and allows similar stream types to share
     * the same functions as appropriate.
     *
     * @internal
     *
     * @return  void
     */
    protected function _install_wrappers ()
    {
        if ( $this->_type === 0 ) {
            $this->_functions[static::STREAM_TIMEOUTF] = function(){throw new \RuntimeException('timeout() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            $this->_functions[static::STREAM_ACCEPTF]  = function(){throw new \RuntimeException( 'accept() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            $this->_functions[static::STREAM_READF]    = function(){throw new \RuntimeException(   'read() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            $this->_functions[static::STREAM_EOFF]     = function(){throw new \RuntimeException(    'eof() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            $this->_functions[static::STREAM_WRITEF]   = function(){throw new \RuntimeException(  'write() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            $this->_functions[static::STREAM_CLOSEF]   = function(){throw new \RuntimeException(  'close() is not implemented for this type of Datastream: ' . $this->_name, ENOSYS);};
            return;
        }
        //  read():
        if ( $this->_type === Datastream::STREAM_FILE || $this->_name === 'STDIN' ) {
            $this->_functions[static::STREAM_READF] = function(){
                if ( $this->_state & Datastream::STREAM_WRITABLE ) {
                    if ( $this->_state & Datastream::STREAM_READABLE ) {
                        //  The first read() operation for a file makes the file
                        //  read-only for the remainder of the stream.
                        $this->_state &= ~Datastream::STREAM_WRITABLE;
                    }
                    else {
                        throw new \RuntimeException("Can't read() from this stream because it is write-only", EACCESS);
                    }
                }
                if ( $this->_flags & static::STREAMOPT_LINEMODE ) {
                    return @fgets($this->_connection, 8192);
                }
                else if ( $this->_flags & static::STREAMOPT_CHARMODE ) {
                    return @fgetc($this->_connection);
                }
                else {
                    return @fread($this->_connection, 1);
                }
            };
            if ( $this->_name === 'STDIN' ) {
                //  STDIN should be made non-blocking by default.
                @stream_set_blocking($this->_connection, 0);
                //  STDIN is also read-only.
                $this->_state &= ~Datastream::STREAM_WRITABLE;
            }
        }
        //  eof():
        if ( $this->_type === Datastream::STREAM_FILE || $this->_name === 'STDOUT' || $this->_name === 'STDERR' ) {
            $this->_functions[static::STREAM_EOFF] = function(){
                return feof($this->_connection);
            };
        }
        //  write():
        if ( $this->_type === Datastream::STREAM_FILE || $this->_name === 'STDOUT' || $this->_name === 'STDERR' ) {
            $this->_functions[static::STREAM_WRITEF] = function(){
                if ( $this->_state & Datastream::STREAM_READABLE ) {
                    if ( $this->_state & Datastream::STREAM_WRITABLE ) {
                        //  The first write() operation for a file makes the file
                        //  write-only for the remainder of the stream.
                        $this->_state &= ~Datastream::STREAM_READABLE;
                        if ( $this->_type === Datastream::STREAM_FILE ) {
                            //  Also, move the file pointer to the end of the file
                            //  by default.
                            fseek($this->_connection, 0, SEEK_END);
                        }
                    }
                    else {
                        throw new \RuntimeException("Can't write() to this stream because it is read-only", EACCESS);
                    }
                }
                if ( strlen($this->_write_buffer) < 1 ) {
                    return 0;
                }
                if ( ($bytes = @fwrite($this->_connection, $this->_write_buffer)) > 0 ) {
                    $this->_write_buffer = (string) substr($this->_write_buffer, $bytes);
                }
                return $bytes;
            };
            if ( $this->_name === 'STDOUT' || $this->_name === 'STDERR' ) {
                //  These pipes are write-only.
                $this->_state &= ~Datastream::STREAM_READABLE;
            }
        }
        //  close():
        if ( $this->_name === 'STDOUT' || $this->_name === 'STDERR' || $this->_name === 'STDIN' ) {
            $this->_functions[static::STREAM_CLOSEF] = function(){
                return true;
            };
        }
        else if ( $this->_type === Datastream::STREAM_FILE ) {
            $this->_functions[static::STREAM_CLOSEF] = function(){
                @fclose($this->_connection);
            };
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
     * @param   int         $operation
     *
     * @internal
     *
     * @return  mixed
     */
    protected function _poll ($operation)
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
        $results = array();
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
                if ( $this->_timeout < 0 || ($this->_timeout >= 0 && $elapsed >= $this->_timeout) ) {
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
     * Return a new \Asinius\ResourceDatastream.
     *
     * @param   mixed       $resource
     *
     * @throws  \RuntimeException
     *
     * @return  \Asinius\ResourceDatastream
     */
    public function __construct ($resource)
    {
        //  Set an error condition now so that no further operations will be
        //  successfully executed if the constructor doesn't complete successfully.
        $this->_state |= Datastream::STREAM_ERROR;
        $this->_install_wrappers();
        if ( is_string($resource) ) {
            $this->_name = $resource;
            if ( ($resource === 'STDIN' || $resource === 'STDOUT' || $resource === 'STDERR') && ! defined($resource) ) {
                //  This pipe needs to be opened, if possible, before continuing.
                switch ($resource) {
                    case 'STDIN':
                        $pipe = fopen('php://stdin', 'r');
                        if ( $pipe === false ) {
                            throw new \RuntimeException("Can't open $resource for reading in this environment");
                        }
                        break;
                    case 'STDOUT':
                    case 'STDERR':
                        $pipe = fopen(sprintf('php://%s', strtolower($resource)), 'a');
                        if ( $pipe === false ) {
                            throw new \RuntimeException("Can't open $resource for writing in this environment");
                        }
                       break;
                    default:
                        throw new \RuntimeException('Reached impossible code path');
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
                    $this->_path = @realpath($resource);
                    if ( $this->_path === false ) {
                        //  1. If the file doesn't already exist, then the parent
                        //  directory must exist and must be writable.
                        $parent_dir = @realpath(dirname($resource));
                        if ( $parent_dir === false ) {
                            throw new \RuntimeException("File at $resource doesn't exist and it can't be created because the parent directory doesn't exist", ENOENT);
                        }
                        if ( ! is_writable($parent_dir) ) {
                            throw new \RuntimeException("File at $resource doesn't exist and it can't be created because the parent directory isn't writable", EACCESS);
                        }
                        //  Parent directory exists and is writable, continue.
                        $this->_path = implode(DIRECTORY_SEPARATOR, [$parent_dir, basename($resource)]);
                    }
                    if ( strpos($this->_path, getcwd()) === 0 ) {
                        //  2. Paths can not reference a location in the application's
                        //  current directory. It's not safe for a library to implicitly
                        //  enable access to files under the current directory because
                        //  there's no way to know if the input is from a trusted or
                        //  untrusted source. If applications want to use this class
                        //  to access files in their own directory, just fopen() the
                        //  path first and pass the resource handle instead.
                        throw new \RuntimeException("The application tried to access a file in its own directory. Wait, that's illegal", EACCESS);
                    }
                    $this->_type = Datastream::STREAM_FILE;
                    $this->_state |= (Datastream::STREAM_READABLE | Datastream::STREAM_WRITABLE);
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
                    throw new \RuntimeException("Can't create a " . __CLASS__ . ' from this resource type: ' . \Asinius\Functions::to_str(@get_resource_type($resource)));
            }
        }
        else {
            throw new \RuntimeException("Can't create a " . __CLASS__ . ' from this: ' . \Asinius\Functions::to_str($resource), EINVAL);
        }
        //  Set the unopened flag and unset the error flag.
        $this->_state |= Datastream::STREAM_UNOPENED;
        $this->_state &= ~Datastream::STREAM_ERROR;
        if ( $this->_type == Datastream::STREAM_FILE ) {
            $this->set(['mode' => 'line']);
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
     * @throws  \RuntimeException
     *
     * @return  void
     */
    public function open ()
    {
        if ( $this->_state & Datastream::STREAM_CLOSED ) {
            throw new \RuntimeException('Datastream has been closed', ENOTCONN);
        }
        else if ( $this->_state & Datastream::STREAM_ERROR ) {
            throw new \RuntimeException("Datastream can't be opened due to a previous error", EHALTED);
        }
        else if ( ! ($this->_state & Datastream::STREAM_UNOPENED) ) {
            return;
        }
        if ( $this->_connection === null && strlen($this->_path) > 0 ) {
            //  Open a file resource at the saved path.
            //  This path has already been validated in__construct(), all that
            //  remains now is to open it in the best way available.
            if ( ! @file_exists($this->_path) ) {
                if ( ($this->_connection = @fopen($this->_path, 'w')) === false ) {
                    $this->_state |= Datastream::STREAM_ERROR;
                    throw new \RuntimeException('Failed to open file at ' . $this->_path . ' for writing', EACCESS);
                }
                //  This path can only be writable.
                $this->_state &= ~Datastream::STREAM_READABLE;
            }
            else if ( ! @is_writable($this->_path) ) {
                if ( ($this->_connection = @fopen($this->_path, 'r')) === false ) {
                    $this->_state |= Datastream::STREAM_ERROR;
                    throw new \RuntimeException('Failed to open file at ' . $this->_path . ' for reading', EACCESS);
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
                    throw new \RuntimeException('Failed to open file at ' . $this->_path, EACCESS);
                }
            }
            $this->_close_when_done = true;
        }
        if ( ! @is_resource($this->_connection) ) {
            //  This code path should be impossible, but something someday will
            //  land here I'm sure.
            $this->_state |= Datastream::STREAM_ERROR;
            throw new \RuntimeException('Stream does not exist', ENOTCONN);
        }
        //  Install the mid-level stream operation functions that are appropriate
        //  for this type of stream.
        $this->_install_wrappers();
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
    public function ready ()
    {
        if ( (! ($this->_state & Datastream::STREAM_ERROR)) && (($this->_state & Datastream::STREAM_CONNECTED) || ($this->_state & Datastream::STREAM_UNOPENED)) ) {
            //  It's okay to consider STREAM_UNOPENED to be "ready" here; the
            //  next read() or write() call will implicitly open the datastream.
            return true;
        }
        return false;
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
     * TODO
     *
     * @return  boolean
     */
    public function empty ()
    {
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new \RuntimeException('eof(): stream is not connected', ENOTCONN);
        }
        if ( $this->_type === Datastream::STREAM_FILE ) {
            //  Don't poll() files.
            return $this->_functions[static::STREAM_EOFF]();
        }
        return $this->_poll(static::STREAM_EOFF);
    }


    /**
     * Return the next bytes, page, row, line, element, etc. Return null when
     * there is no more data to return.
     *
     * @return  mixed
     */
    public function read ($count = 1)
    {
        //  Load the requested data into the read buffer if it's not already
        //  available there.
        $out = $this->peek($count);
        //  Empty the read buffer after retrieving its contents.
        if ( is_array($this->_read_buffer) ) {
            //  Line mode.
            array_splice($this->_read_buffer, 0, count($out));
        }
        else {
            //  Character mode.
            $this->_read_buffer = mb_substr($this->_read_buffer, mb_strlen($out));
        }
        if ( $this->_flags & static::STREAMOPT_COUNTLINES ) {
            if ( is_array($this->_read_buffer) ) {
                $this->_line += count($out);
                $this->_position = 0;
            }
            else {
                $i = 0;
                while ( mb_strpos($out, "\n", $i) !== false ) {
                    $this->_line++;
                    $this->_position = 0;
                    $i++;
                }
                $this->_position += mb_strlen($out) - $i;
            }
        }
        return $out;
    }


    /**
     * Similar to read(), but the internal data buffer is not emptied. This
     * allows the application to "preview" the next chunk of data in the stream.
     *
     * @return  mixed
     */
    public function peek ($count = 1)
    {
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new \RuntimeException('read(): stream is not connected', ENOTCONN);
        }
        $last_count = -1;
        if ( is_array($this->_read_buffer) ) {
            //  Line mode. Fill the read buffer until it has $count lines in it.
            $n = count($this->_read_buffer);
            while ( $last_count < $n && $n < $count ) {
                $last_count = $n;
                if ( $this->_type === Datastream::STREAM_FILE ) {
                    //  Don't poll() files.
                    $this->_read_buffer[] = $this->_functions[static::STREAM_READF]();
                }
                else {
                    $this->_read_buffer[] = implode('', $this->_poll(static::STREAM_READF));
                }
                $n = count($this->_read_buffer);
            }
            //  Return the first $count elements of the read buffer.
            return array_slice($this->_read_buffer, 0, $count);
        }
        else {
            //  Character mode. Fill the read buffer until it has $count
            //  characters in it.
            $n = mb_strlen($this->_read_buffer);
            while ( $last_count < $n && $n < $count ) {
                $last_count = $n;
                if ( $this->_type === Datastream::STREAM_FILE ) {
                    //  Don't poll() files.
                    $this->_read_buffer .= $this->_functions[static::STREAM_READF]();
                }
                else {
                    $this->_read_buffer .= implode('', $this->_poll(static::STREAM_READF));
                }
                $n = mb_strlen($this->_read_buffer);
            }
            //  Return the first $count characters of the read buffer.
            return mb_substr($this->_read_buffer, 0, $count);
        }
        return $this->_read_buffer;
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
        if ( $this->_state & Datastream::STREAM_UNOPENED ) {
            $this->open();
        }
        if ( ! $this->ready() ) {
            throw new \RuntimeException('write(): stream is not connected', ENOTCONN);
        }
        $this->_write_buffer .= $data;
        return $this->_functions[static::STREAM_WRITEF]();
    }


    /**
     * Set one or more options on this stream.
     *
     * @param   array   $options
     *
     * @return  void
     */
    public function set ($options)
    {
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'mode':
                    switch ($value) {
                        case 'line':
                            $this->_flags |= static::STREAMOPT_LINEMODE;
                            $this->_flags &= ~static::STREAMOPT_CHARMODE;
                            $this->_read_buffer = preg_split('/\n/', $this->_read_buffer);
                            break;
                        case 'char':
                            $this->_flags |= static::STREAMOPT_CHARMODE;
                            $this->_flags &= ~static::STREAMOPT_LINEMODE;
                            $this->_read_buffer = implode('\n', $this->_read_buffer);
                            break;
                        default:
                            throw new \RuntimeException("\"$value\" is not a valid value for a stream mode option");
                    }
                    break;
                case 'line-tracking':
                    switch ($value) {
                        case true:
                        case 'on':
                            $this->_flags |= static::STREAMOPT_COUNTLINES;
                            if ( ($this->_flags & static::STREAMOPT_CHARMODE) && $this->_line == 0 ) {
                                $this->_line = 1;
                            }
                            break;
                        case false:
                        case 'off':
                            $this->_flags &= ~static::STREAMOPT_COUNTLINES;
                            break;
                    }
                    break;
            }
        }
    }


    /**
     * Return the current line number and position of the datastream, if line
     * tracking has been enabled.
     *
     * @return  array
     */
    public function position ()
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
        $this->_functions[static::STREAM_CLOSEF]();
        $this->_state |= Datastream::STREAM_CLOSED;
    }

}
