<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Datastream                                                         *
*                                                                              *
*   An abstraction layer for things that do I/O. Typically used if an          *
*   application wants to be able to open some URL and just do things with it   *
*   without getting into the nitty-gritty details of the protocol.             *
*                                                                              *
*   Classes that do a good job of implementing this interface can be coupled   *
*   together so that a read from one Datastream can be piped directly into     *
*   the write for another Datastream.
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2023 Rob Sheldon <rob@robsheldon.com>                        *
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


/*******************************************************************************
*                                                                              *
*   \Asinius\Datastream                                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius {

    interface Datastream
    {

        //  Stream types.
        const   STREAM_GENERIC          = 1;
        const   STREAM_BASIC            = 2;
        const   STREAM_UNIX             = 4;
        const   STREAM_PIPE             = 8;
        const   STREAM_FILE             = 16;
        const   STREAM_TCP              = 32;
        const   STREAM_SOCKET           = 64;
        const   STREAM_UNSUPPORTED      = 128;
        const   STREAM_TYPEMASK         = 0xff;

        //  Stream states.
        const   STREAM_UNOPENED         = 256;
        const   STREAM_ERROR            = 512;
        const   STREAM_LISTENING        = 1024;
        const   STREAM_CONNECTED        = 2048;
        const   STREAM_CLOSED           = 4096;
        const   STREAM_READABLE         = 8192;
        const   STREAM_WRITABLE         = 16384;
        const   STREAM_STATEMASK        = 0xff00;

        //  Variable timeout.
        const   STREAM_VARY_TIMEOUT     = 65536;

        //  Buffer size, set to the size of a typical OS memory page by default.
        const   STREAM_BUFFER_SIZE      = 4096;

        //  I/O load and stream sleep magic numbers.
        const   IO_LOAD_0               = -PHP_INT_MAX>>1<<1;
        const   IO_LOAD_LOW             = (-PHP_INT_MAX>>1 ^ -PHP_INT_MAX)>>5;
        const   IO_LOAD_HIGH            = 1<<5;
        const   STREAM_SLEEP_MIN        = 0b00000000000000011111;
        const   STREAM_SLEEP_MAX        = 0b11111000000000000000;


        /**
         * Datastreams are an abstraction for some data endpoint.
         */
        public function __construct ($endpoint);

        /**
         * Datastreams must implement a __destruct() function that cleanly shuts
         * down the endpoint.
         */
        public function __destruct ();

        /**
         * Datastreams must include complete support for custom properties.
         * 
         * @param   string      $property
         */
        public function __get (string $property);

        /**
         * Datastreams must include complete support for custom properties.
         * 
         * @param   string      $property
         * @param   mixed       $value
         */
        public function __set (string $property, $value);

        /**
         * Datastreams must include complete support for custom properties.
         * 
         * @param   string      $property
         * 
         * @return  boolean
         */
        public function __isset (string $property) : bool;

        /**
         * Datastreams must not open their endpoint in the constructor, so that the
         * application has an opportunity to set any custom properties as necessary
         * before opening the endpoint.
         */
        public function open ();

        /**
         * Datastreams must support a ready() function that indicates the Datastream
         * is not currently stuck in an error condition and is ready to handle data.
         *
         * @return  boolean
         */
        public function ready ();

        /**
         * Datastreams must implement a search() function that allows the application
         * to quickly fast-forward the Datastream until some query is satisfied.
         */
        public function search ($query);

        /**
         * Return true if there is nothing more to read(), false otherwise.
         */
        public function empty ();

        /**
         * Return the next bytes, page, row, line, element, etc. Return null when
         * there is no more data to return.
         */
        public function read ();

        /**
         * Similar to read(), but the internal data buffer is not changed. This
         * allows the application to "preview" the next chunk of data in the stream.
         */
        public function peek ();

        /**
         * Datastreams should buffer content as much as possible and allow application
         * components to "rewind" the buffer some number of lines, bytes, rows, or
         * other units of data when necessary.
         */
        public function rewind ();

        /**
         * Datastreams must implement a function that can accept data to be sent
         * to the other end of the stream.
         */
        public function write ($data);

        /**
         * Datastreams must implement a function that will cleanly shut down the
         * stream and mark it as such, preventing any further reads or writes.
         */
        public function close ();

    }

}


/*******************************************************************************
*                                                                              *
*   DatastreamProperties trait                                                 *
*                                                                              *
*******************************************************************************/

namespace Asinius\Datastream {

    trait Properties
    {
        //  The DatastreamProperties trait provides all of the code necessary for
        //  handling normal, lazy-loaded, or read-only properties in a class that
        //  implements the Datastream interface.

        protected $_properties = [
            'restricted'    => true,        //  Prevent outside code from adding any new properties.
            'values'        => [],          //  Storage for property values.
        ];

        //  The CallerInfo trait is used here for controlled access to properties.
        use CallerInfo;


        /**
         * Return the value of a property. If the property doesn't already exist in
         * $this->_properties, then a "get_[property]" function will called, if
         * available.
         * 
         * @param   string      $property
         *
         * @throws  RuntimeException
         * 
         * @return  mixed
         */
        public function __get (string $property)
        {
            if ( array_key_exists($property, $this->_properties['values']) ) {
                return $this->_properties['values'][$property]['value'];
            }
            if ( is_callable([$this, "get_$property"]) ) {
                return call_user_func([$this, "get_$property"]);
            }
            throw new RuntimeException("Undefined property: " . __CLASS__ . "->\$$property");
        }


        /**
         * Returns true if the given property exists, false otherwise.
         *
         * @param   string      $property
         *
         * @return  boolean
         */
        public function __isset (string $property) : bool
        {
            return array_key_exists($property, $this->_properties['values']) || is_callable([$this, "get_$property"]);
        }


        /**
         * Change the value for a property if:
         *     - The property doesn't exist and properties are unrestricted;
         *     - The property exists but is not locked;
         *     - The change is coming from a function call in the current object.
         * Otherwise, throw an error.
         *
         * If a "set_*" function exists for the property, call that instead of
         * setting it directly, unless it's this object trying to set one of its
         * own properties.
         *
         * @param   string      $property
         * @param   mixed       $value
         *
         * @throws  RuntimeException
         *
         * @return  void
         */
        public function __set (string $property, $value)
        {
            if ( ! array_key_exists($property, $this->_properties['values']) ) {
                if ( $this->_properties['restricted'] && ! static::_caller_is($this) ) {
                    throw new RuntimeException("Undefined property: " . __CLASS__ . "->\$$property (properties for this object are currently restricted)");
                }
                $this->_properties['values'][$property] = ['value' => $value, 'locked' => false];
            }
            else if ( $this->_properties['values'][$property]['locked'] && ! static::_caller_is($this) ) {
                throw new RuntimeException("Can't set " . __CLASS__ . "->\$$property: this property is currently read-only");
            }
            else if ( is_callable([$this, "set_$property"]) && ! static::_caller_is($this) ) {
                return call_user_func([$this, "set_$property"], $value);
            }
            else {
                $this->_properties['values'][$property]['value'] = $value;
            }
        }


        /**
         * Return true if a property value exists.
         *
         * @param   string      $property
         *
         * @return  boolean
         */
        protected function _property_exists (string $property) : bool
        {
            return array_key_exists($property, $this->_properties['values']);
        }


        /**
         * Lock an existing property, preventing its value from being changed unless
         * the changes are coming from code in this object.
         *
         * @param  string       $property
         *
         * @throws RuntimeException
         *
         * @return void
         */
        protected function _lock_property (string $property)
        {
            if ( ! array_key_exists($property, $this->_properties['values']) ) {
                throw new RuntimeException("Can't lock " . __CLASS__ . "->\$$property: this property does not exist");
            }
            $this->_properties['values'][$property]['locked'] = true;
        }


        /**
         * Unlock an existing property, allowing it to be changed by external code.
         *
         * @param  string       $property
         *
         * @throws RuntimeException
         *
         * @return void
         */
        protected function _unlock_property (string $property)
        {
            if ( ! array_key_exists($property, $this->_properties['values']) ) {
                throw new RuntimeException("Can't unlock " . __CLASS__ . "->\$$property: this property does not exist");
            }
            $this->_properties['values'][$property]['locked'] = false;
        }


        /**
         * Prevent external code from adding any new properties to this object.
         * The object can still add new properties to itself.
         *
         * @return void
         */
        protected function _restrict_properties ()
        {
            $this->_properties['restricted'] = true;
        }


        /**
         * Allow external code to add new properties to this object.
         *
         * @return void
         */
        protected function _unrestrict_properties ()
        {
            $this->_properties['restrictied'] = false;
        }

    }


/*******************************************************************************
*                                                                              *
*   DatastreamLogging trait                                                    *
*                                                                              *
*******************************************************************************/

    trait Logging
    {
        //  The DatastreamLogging trait provides all of the code that Datastreams
        //  need to store, broadcast, and return errors and other messages.

    }

}
