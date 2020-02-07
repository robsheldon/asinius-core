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
*   \Asinius\Datastream                                                        *
*                                                                              *
*******************************************************************************/

interface Datastream
{

    const   STATUS_READY    =  1;
    const   STATUS_ERROR    =  0;
    const   STATUS_CLOSED   = -1;

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
    public function __get ($property);

    /**
     * Datastreams must include complete support for custom properties.
     * 
     * @param   string      $property
     * @param   mixed       $value
     */
    public function __set ($property, $value);

    /**
     * Datastreams must include complete support for custom properties.
     * 
     * @param   string      $property
     * 
     * @return  boolean
     */
    public function __isset ($property);

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
     * Datastreams must maintain an error log and provide read-only access to it.
     */
    public function errors ();

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
