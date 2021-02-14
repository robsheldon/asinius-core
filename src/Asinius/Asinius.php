<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Asinius                                                            *
*                                                                              *
*   Miscellaneous methods used by other classes.                               *
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
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

//  Error codes familiar to C programmers.
//  (from https://web.archive.org/web/20180731183139/http://www.virtsync.com/c-error-codes-include-errno)

defined('EUNDEF')       or define('EUNDEF', -1);       //  Non-specific; try to avoid
defined('ENOENT')       or define('ENOENT', 2);        //  File not found
defined('EWOULDBLOCK')  or define('EWOULDBLOCK', 11);  //  Operation would block
defined('EACCESS')      or define('EACCESS', 13);      //  Permission/access denied
defined('EEXIST')       or define('EEXIST', 17);       //  File exists
defined('ENOTDIR')      or define('ENOTDIR', 20);      //  Not a directory
defined('EINVAL')       or define('EINVAL', 22);       //  Invalid function argument
defined('ENOSYS')       or define('ENOSYS', 38);       //  Function not implemented
defined('ENODATA')      or define('ENODATA', 61);      //  No data available
defined('EFTYPE')       or define('EFTYPE', 79);       //  Wrong file type
defined('ENOTCONN')     or define('ENOTCONN', 107);    //  Endpoint not connected
//  Custom error conditions.
defined('EPARSE')       or define('EPARSE', 201);      //  Parse error
defined('ENOTFILE')     or define('ENOTFILE', 202);    //  Not a regular file
defined('EHALTED')      or define('EHALTED', 203);     //  Can't execute because of a previous error
defined('ENOCONFIG')    or define('ENOCONFIG', 254);   //  Not configured


/*******************************************************************************
*                                                                              *
*   \Asinius\Asinius                                                           *
*                                                                              *
*******************************************************************************/

class Asinius
{


    private static $_class_files    = false;
    private static $_path_prefix    = '';


    /**
     * Register the autoloader. This only gets used in manual installations
     * (without Composer). Do not call this in a Composer installation.
     *
     * @return  void
     */
    public static function init_autoloader ()
    {
        if ( is_array(self::$_class_files) ) {
            return;
        }
        spl_autoload_register(['self', 'autoload'], true);
        self::$_class_files = [];
        //  The path to this file should look something like
        //  blah/blah/asinius/core/src/Asinius/Asinius.php
        //  Capture the "blah/blah/asinius" part.
        self::$_path_prefix = realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..']));
        if ( self::$_path_prefix === false ) {
            //  It's tempting to set this to some string and continue, but this
            //  is a situation that at best will break the autoloader in weird
            //  ways and at worst could cause a path traversal security issue.
            throw new \RuntimeException("Can't find path to Asinius components. Asinius.php should be in core/src/Asinius/Asinius.php somewhere in your project.");
        }
    }


    /**
     * Autoloader for Asinius library classes. This function lazy-loads the
     * directory structure for its various class files as they are requested.
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
        $path = self::$_path_prefix;
        //  Construct Composer-compatible PSR-4 path to the requested component.
        //  If $classname was something like, "Asinius\Thing", then assume that
        //  it's a core component.
        //  Otherwise, e.g. "Asinius\HTTP\Thing" should be in asinius/http/src/HTTP/Thing.php.
        if ( count($classfile) < 2 ) {
            $path .= implode(DIRECTORY_SEPARATOR, ['', 'core', 'src', 'Asinius']);
        }
        else {
            $path .= implode(DIRECTORY_SEPARATOR, ['', strtolower($classfile[0]), 'src']);
        }
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
                    throw new \RuntimeException("File not accessible: $path");
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
     * Check the call stack to ensure that an object was instantiated by a specific class.
     *
     * @param   string      $classes
     * 
     * @throws  RuntimeException
     *
     * @internal
     * 
     * @return  void
     */
    public static function assert_parent ($classes)
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
        if ( count($classes) < 2 ) {
            $class = $classes[0];
            throw new \RuntimeException("$child_class must be instantiated by the $class class");
        }
        else {
            $classes = implode(', ', $classes);
            throw new \RuntimeException("$child_class must be instantiated by one of the following classes: $classes");
        }
    }


}



/*******************************************************************************
*                                                                              *
*   CallerInfo trait                                                           *
*                                                                              *
*******************************************************************************/

trait CallerInfo
{

    /**
     * Returns true if the caller matches an object or static class reference.
     *
     * @param   mixed       $reference
     *
     * @return  boolean
     */
    protected static function _caller_is ($reference)
    {
        if ( ! is_array($reference) ) {
            $reference = [$reference];
        }
        $stack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        //  If there are only two entries in the stack, then the function call
        //  came from application code.
        if ( count($stack) < 3 ) {
            return false;
        }
        $caller = array_pop($stack);
        //  Match the calling class or object against the reference(s).
        //  Use _caller_is($this) to force the comparison to match the current
        //  object exactly.
        return ($caller['type'] == '->' || $caller['type'] == '::') && ((isset($caller['object']) && in_array($caller['object'], $reference, true)) || (isset($caller['class']) && in_array($caller['class'], $reference, true)));
    }
}



/*******************************************************************************
*                                                                              *
*   DatastreamProperties trait                                                 *
*                                                                              *
*******************************************************************************/

trait DatastreamProperties
{
    //  The DatastreamProperties trait provides all of the code necessary for
    //  handling normal, lazy-loaded, or read-only properties in a class that
    //  implements the Datastream interface.

    protected $_properties = [
        'restricted'    => true,        //  Prevent outside code from adding any new properties.
        'values'        => [],          //  Storage for property values.
    ];

    //  The CallerInfo trait is used here for controlled access to properties.
    use \Asinius\CallerInfo;


    /**
     * Return the value of a property. If the property doesn't already exist in
     * $this->_properties, then a "get_[property]" function will called, if
     * available.
     * 
     * @param   string      $property
     *
     * @throws  \RuntimeException
     * 
     * @return  mixed
     */
    public function __get ($property)
    {
        if ( array_key_exists($property, $this->_properties['values']) ) {
            return $this->_properties['values'][$property]['value'];
        }
        if ( is_callable([$this, "get_$property"]) ) {
            return call_user_func([$this, "get_$property"]);
        }
        throw new \RuntimeException("Undefined property: " . __CLASS__ . "->\$$property");
    }


    /**
     * Returns true if the given property exists, false otherwise.
     *
     * @param   string      $property
     *
     * @return  boolean
     */
    public function __isset ($property)
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
     * @throws  \RuntimeException
     *
     * @return  void
     */
    public function __set ($property, $value)
    {
        if ( ! array_key_exists($property, $this->_properties['values']) ) {
            if ( $this->_properties['restricted'] && ! static::_caller_is($this) ) {
                throw new \RuntimeException("Undefined property: " . __CLASS__ . "->\$$property (properties for this object are currently restricted)");
            }
            $this->_properties['values'][$property] = ['value' => $value, 'locked' => false];
        }
        else if ( $this->_properties['values'][$property]['locked'] && ! static::_caller_is($this) ) {
            throw new \RuntimeException("Can't set " . __CLASS__ . "->\$$property: this property is currently read-only");
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
     * @return  boolean
     */
    protected function _property_exists ($property)
    {
        return array_key_exists($property, $this->_properties['values']);
    }


    /**
     * Lock an existing property, preventing its value from being changed unless
     * the changes are coming from code in this object.
     *
     * @param  string       $property
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function _lock_property ($property)
    {
        if ( ! array_key_exists($property, $this->_properties['values']) ) {
            throw new \RuntimeException("Can't lock " . __CLASS__ . "->\$$property: this property does not exist");
        }
        $this->_properties['values'][$property]['locked'] = true;
    }


    /**
     * Unlock an existing property, allowing it to be changed by external code.
     *
     * @param  string       $property
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function _unlock_property ($property)
    {
        if ( ! array_key_exists($property, $this->_properties['values']) ) {
            throw new \RuntimeException("Can't unlock " . __CLASS__ . "->\$$property: this property does not exist");
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

trait DatastreamLogging
{
    //  The DatastreamLogging trait provides all of the code that Datastreams
    //  need to store, broadcast, and return errors and other messages.

}
