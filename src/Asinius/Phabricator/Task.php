<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Phabricator\Task                                                   *
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
*   \Asinius\Phabricator\Task                                                  *
*                                                                              *
*******************************************************************************/

class Task
{

    private $_properties    = [];
    private $_client        = null;


    /**
     * Create a new Phabricator Task object from a set of properties.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     * 
     * @param   array       $properties
     * @param   Client      $client
     *
     * @throws  RuntimeException
     *
     * @internal
     *
     * @return  \Asinius\Phabricator\Task
     */
    public function __construct ($properties, $client)
    {
        \Asinius\Asinius::enforce_created_by('\Asinius\Phabricator\Client');
        $this->_client = $client;
        $this->_properties = $properties['fields'];
        unset($properties['fields']);
        $this->_properties['attachments'] = $properties['attachments'];
        unset($properties['attachments']);
        $this->_properties = array_merge($this->_properties, $properties);
    }


    /**
     * Return the value of a Phabricator Task property.
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
        if ( $property == 'commits' ) {
            $this->_properties['commits'] = $this->_client->commits(['task_phids' => $this->_properties['phid']]);
            return $this->_properties['commits'];
        }
        throw new \RuntimeException("Undefined property: $property");
    }


    /**
     * Return any commits associated with this task.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @throws  RuntimeException
     * 
     * @return  array
     */
    public function commits ()
    {
        return $this->_client->commits(['task_phids' => $this->_properties['phid']]);
    }

}
