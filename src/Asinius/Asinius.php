<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Asinius                                                            *
*                                                                              *
*   Miscellaneous methods used by other classes.                               *
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

namespace Asinius;

/*******************************************************************************
*                                                                              *
*   \Asinius\Asinius                                                           *
*                                                                              *
*******************************************************************************/

class Asinius
{


    /**
     * Check the call stack to ensure that a class was instantiated by another
     * class.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $classes
     * 
     * @throws  RuntimeException
     *
     * @internal
     * 
     * @return  void
     */
    public static function enforce_created_by ($classes)
    {
        if ( is_string($classes) ) {
            $classes = [$classes];
        }
        $stack_trace = debug_backtrace();
        array_shift($stack_trace);
        $child_class = '';
        foreach ($stack_trace as $caller) {
            if ( array_key_exists('object', $caller) ) {
                if ( empty($child_class) ) {
                    $child_class = get_class($caller['object']);
                    continue;
                }
                foreach ($classes as $class) {
                    if ( is_a($caller['object'], $class) ) {
                        return;
                    }
                }
            }
            else if ( array_key_exists('class', $caller) && array_key_exists('type', $caller) && $caller['type'] == '::' ) {
                if ( empty($child_class) ) {
                    $child_class = $caller['class'];
                    continue;
                }
                if ( in_array($caller['class'], $classes) ) {
                    return;
                }
            }
        }
        if ( empty($child_class) ) {
            $child_class = 'Object';
        }
        throw new \RuntimeException("$child_class must be instantiated by the $class class");
    }


}
