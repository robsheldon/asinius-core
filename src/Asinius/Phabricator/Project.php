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

class Project extends PhObject
{


    /**
     * Return any tasks associated with this project.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  array
     */
    public function tasks ($parameters)
    {
        return $this->_client->tasks(array_merge($parameters, ['task_phids' => $this->_properties['phid']]));
    }


    /**
     * Return the workboard for this project.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  \Asinius\Phabricator\Workboard
     */
    public function workboard ()
    {
        $workboards = $this->_client->workboard_columns(['project' => $this]);
        if ( is_array($workboards) && ! empty($workboards) ) {
            //  A project should only have one workboard.
            return array_shift($workboards);
        }
    }


}
