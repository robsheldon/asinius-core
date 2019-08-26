<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Phabricator\Project                                                *
*                                                                              *
*   Class for working with projects through the Phabricator API.               *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2019 Rob Sheldon <rob@robsheldon.com>                        *
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

namespace Asinius\Phabricator;


/*******************************************************************************
*                                                                              *
*   \Asinius\Phabricator\Project                                               *
*                                                                              *
*******************************************************************************/

class Project
{

    private $_properties    = [];


    public function __construct ($properties)
    {
        \Asinius\Object::enforce_created_by('\Asinius\Phabricator\Client');
        $this->_properties = $properties['fields'];
        unset($properties['fields']);
        $this->_properties['attachments'] = $properties['attachments'];
        unset($properties['attachments']);
        $this->_properties = array_merge($this->_properties, $properties);
    }


    /**
     * Return the value of a Phabricator Project property.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $property
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public function __get ($property)
    {
        if ( array_key_exists($property, $this->_properties) ) {
            return $this->_properties[$property];
        }
        throw new \RuntimeException("Undefined property: $property");
    }

}
