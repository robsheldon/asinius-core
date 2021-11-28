<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Network\Address                                                    *
*                                                                              *
*   Read-only class for addresses found in networking contexts (IPv4, IPv6,    *
*   etc.).                                                                     *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2021 Rob Sheldon <rob@robsheldon.com>                        *
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

namespace Asinius\Network;

use RuntimeException;
use Asinius\DatastreamProperties;


/**
 * Asinius\Network\Address encapsulates a network address -- IPv4, IPv6, or MAC.
 *
 * @property    string      $type
 * @property    string      $as_string
 * @property    array       $octets
 */
class Address
{

    //  Address types.
    const   NET_ADDR_UNDEFINED  = 0;
    const   NET_ADDR_IP4        = 1;
    const   NET_ADDR_IP6        = 2;
    const   NET_ADDR_MAC        = 3;

    use DatastreamProperties;

    /**
     * Return a new \Asinius\Network\Address
     *
     * @param   mixed       $address
     *
     * @throws  RuntimeException
     */
    public function __construct ($address)
    {
        //  This class constructor here is essentially a gigantic network address
        //  validator.
        if ( is_int($address) ) {
            $this->__set('type', Address::NET_ADDR_IP4);
            $this->__set('as_string', long2ip($address));
            $this->__set('octets', unpack('C*', inet_pton($this->__get('as_string'))));
        }
        else if ( is_string($address) ) {
            $this->__set('type', Address::NET_ADDR_UNDEFINED);
            $input = $address;
            $address = strtolower($address);
            if (
                filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_NULL_ON_FAILURE) !== null ||
                //	filter_var(..., FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) breaks
                //	on IPs with octet values of 0 in some versions of PHP.
                preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $address) && count(array_filter(explode('.', $address), function($o){return ($o = intval($o)) >= 0 && $o <= 255;})) === 4
            ) {
                $this->__set('type', Address::NET_ADDR_IP4);
                $this->__set('octets', array_values(unpack('C*', inet_pton($address))));
            }
            else if ( filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_NULL_ON_FAILURE) !== null ) {
                $this->__set('type', Address::NET_ADDR_IP6);
                $this->__set('octets', array_values(unpack('C*', inet_pton($address))));
            }
            else if ( filter_var($address, FILTER_VALIDATE_MAC, FILTER_NULL_ON_FAILURE) !== null ) {
                $this->__set('type', Address::NET_ADDR_MAC);
                $this->__set('octets', array_map('hexdec', explode(':', $address)));
            }
            if ( $this->__get('type') === Address::NET_ADDR_UNDEFINED ) {
                throw new RuntimeException("Not a valid network address: $input");
            }
            $this->__set('as_string', $address);
        }
        else {
            throw new RuntimeException('Input type not supported: ' . gettype($address));
        }
        //  Make this object read-only.
        $this->_lock_property('type');
        $this->_lock_property('as_string');
        $this->_lock_property('octets');
        $this->_restrict_properties();
    }


    /**
     * Return the text form of the network address in a string context.
     *
     * @return  string
     */
    public function __toString() {
        return $this->__get('as_string');
    }

}
