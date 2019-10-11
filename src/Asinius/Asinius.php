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


    private static $_class_files    = false;


    /**
     * Register the autoloader. This only gets used in manual installations
     * (without Composer). Do not call this in a Composer installation.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  void
     */
    public static function init_autoloader ()
    {
        if ( is_array(self::$_class_files) ) {
            return;
        }
        spl_autoload_register(['self', 'autoload'], true);
    }


    /**
     * Autoloader for Asinius library classes. This function lazy-loads the
     * directory structure for its various class files as they are requested.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $classsname
     *
     * @internal
     *
     * @return  void
     */
    private static function autoload ($classname)
    {
        if ( ! is_array(self::$_class_files) ) {
            self::init_autoloader();
        }
        $classfile = explode('\\', $classname . '.php');
        if ( __NAMESPACE__ . '\\' . array_shift($classfile) != __CLASS__ ) {
            return;
        }
        $path = __DIR__;
        //  Grab a reference to the local file cache to make recursive
        //  updates possible.
        $cached = &self::$_class_files;
        while ( count($classfile) ) {
            $find = array_shift($classfile);
            if ( empty($cached) ) {
                //  If we are currently descended into a directory that doesn't
                //  already have entries cached for it, then scan the directory
                //  and parse and load the entries into the cache.
                $entry_keys = array_filter(scandir($path), function($entry){
                    return mb_strlen($entry) > 0 && mb_substr($entry, 0, 1) != '.';
                });
                $entry_values = array_map(function($entry) use($path){
                    if ( is_dir($path . DIRECTORY_SEPARATOR . $entry) ) {
                        return [];
                    }
                    return false;
                }, $entry_keys);
                $cached = array_combine($entry_keys, $entry_values);
            }
            //  If the thing we're looking for doesn't exist in this directory,
            //  or if it exists as a file and we're looking for a directory,
            //  then give up.
            if ( ! array_key_exists($find, $cached) || (count($classfile) && $cached[$find] === false) ) {
                return;
            }
            $path .= DIRECTORY_SEPARATOR . $find;
            //  If we've found the thing we're looking for and it's readable,
            //  load it and return.
            if ( count($classfile) == 0 && array_key_exists($find, $cached) ) {
                if ( ! is_readable($path) ) {
                    //  I think it's appropriate to throw an expection here to
                    //  make troubleshooting easier in cases where permissions
                    //  have gotten screwed up.
                    //  This violates PSR-4, but I've seen wonky permissions in
                    //  team environments cause much wailing and gnashing of
                    //  teeth, and this exception will only throw in the specific
                    //  case that a file belonging to this library, matching
                    //  the class path we're looking for, exists but is not
                    //  readable.
                    throw new RuntimeException("File not accessible: $path");
                }
                //  A closure is used here to prevent conflicts and access to
                //  "self" or "$this".
                (function($file){
                    include $file;
                })($path);
                return;
            }
            //  Keep digging.
            $cached = &$cached[$find];
        }
    }


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
