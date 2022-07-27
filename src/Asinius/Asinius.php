<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Asinius                                                            *
*                                                                              *
*   Provides the Asinius library autoloader (for non-Composer projects) and    *
*   the assert_parent() call stack inspection function, along with the         *
*   following traits:                                                          *
*       CallerInfo                                                             *
*       DatastreamProperties                                                   *
*       DatastreamLogging                                                      *
*                                                                              *
*   These traits are commonly used by other library components and disk        *
*   accesses are expensive, so they're all included here.                      *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
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

use RuntimeException;


/*******************************************************************************
*                                                                              *
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

//  Error codes familiar to C programmers.
//  (from https://web.archive.org/web/20180731183139/http://www.virtsync.com/c-error-codes-include-errno)

defined('EUNDEF')       or define('EUNDEF', -1);       //  Non-specific; try to avoid
defined('EPERM')        or define('EPERM', 1);         //  Operation not permitted.
defined('ENOENT')       or define('ENOENT', 2);        //  File not found
defined('EAGAIN')       or define('EAGAIN', 11);       //  Try again?
defined('EWOULDBLOCK')  or define('EWOULDBLOCK', 11);  //  Operation would block
defined('EACCESS')      or define('EACCESS', 13);      //  Permission/access denied
defined('EEXIST')       or define('EEXIST', 17);       //  File exists
defined('ENOTDIR')      or define('ENOTDIR', 20);      //  Not a directory
defined('EINVAL')       or define('EINVAL', 22);       //  Invalid function argument
defined('ENOSYS')       or define('ENOSYS', 38);       //  Function not implemented
defined('ENODATA')      or define('ENODATA', 61);      //  No data available
defined('EFTYPE')       or define('EFTYPE', 79);       //  Wrong file type
defined('ENOTCONN')     or define('ENOTCONN', 107);    //  Endpoint not connected
defined('EALREADY')     or define('EALREADY', 114);    //  Operation already in progress.
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

    //  Used in the str_chunk() function below.
    const DEFAULT_QUOTES   = [["'", "'"], ['"', '"']];
    const DEFAULT_PARENS   = [['(', ')']];
    const DEFAULT_BRACKETS = [['[', ']'], ['{', '}']];

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
        if ( self::$_path_prefix === false || self::$_path_prefix === '' ) {
            //  It's tempting to set this to some string and continue, but this
            //  is a situation that at best will break the autoloader in weird
            //  ways and at worst could cause a path traversal security issue.
            throw new RuntimeException("Can't find path to Asinius components. Asinius.php should be in core/src/Asinius/Asinius.php somewhere in your project.");
        }
    }


    /**
     * Autoloader for Asinius library classes. This function lazy-loads the
     * directory structure for its various class files as they are requested.
     *
     * @param   string      $classname
     *
     * @internal
     *
     * @return  void
     */
    private static function autoload (string $classname)
    {
        if ( ! is_array(self::$_class_files) ) {
            self::init_autoloader();
        }
        $classfile = explode('\\', $classname . '.php');
        if ( __NAMESPACE__ . '\\' . array_shift($classfile) != __CLASS__ ) {
            return;
        }
        //  Construct Composer-compatible PSR-4 path to the requested component.
        //  If $classname was something like, "Asinius\Thing", then assume that
        //  it's a core component.
        //  Otherwise, e.g. "Asinius\HTTP\Thing" should be in asinius/http/src/HTTP/Thing.php.
        if ( count($classfile) < 2 ) {
            $path = implode(DIRECTORY_SEPARATOR, [self::$_path_prefix, 'core', 'src', 'Asinius']);
        }
        else if ( in_array($classfile[0], ['Datastream', 'Document', 'Network']) ) {
            //  These are other classes that are part of the core class hierarchy.
            $path = implode(DIRECTORY_SEPARATOR, [self::$_path_prefix, 'core', 'src', 'Asinius', array_shift($classfile)]);
        }
        else {
            $path = implode(DIRECTORY_SEPARATOR, [self::$_path_prefix, strtolower($classfile[0]), 'src']);
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
     * Check the call stack to ensure that an object was instantiated by a specific class.
     *
     * @param   mixed      $classes
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
            throw new RuntimeException("$child_class must be instantiated by the $class class");
        }
        $classes = implode(', ', $classes);
        throw new RuntimeException("$child_class must be instantiated by one of the following classes: $classes");
    }


    /**************************************************************************
    *                                                                         *
    *   Utility functions.                                                    *
    *                                                                         *
    **************************************************************************/

    /**
     * A combination of strtok() and explode(), with support for quoted chunks.
     * Split $string into up to $limit chunks delimited by any of $delimiters.
     * 
     * If $limit is < 1, then split the entire string.
     * 
     * If $quotes is provided, then sections of $string are not split when enclosed
     * by those characters. $quotes should be an array where each element is an
     * array of opening quote, closing quote.
     *
     * $delimiters can be a simple string, which will cause $string to be split
     * by any of those characters, or it can be an array of strings, which will
     * cause $string to be split only by those exact sequences of characters.
     *
     * Each chunk will begin with the delimiter it was split on unless
     * $strip_delimiter is set to true.
     * 
     * @param   string      $string
     * @param   mixed       $delimiters
     * @param   integer     $limit
     * @param   array       $quotes
     * @param   boolean     $strip_delimiter
     *
     * @throws  RuntimeException
     * 
     * @return  array
     */
    public static function str_chunk (string $string, $delimiters, int $limit = 0, array $quotes = [], bool $strip_delimiter = false) : array
    {
        $chunks = [];
        //  Start by decomposing the string into quoted and unquoted chunks.
        //  This is best done by building a yucky regex for preg_split().
        if ( ! empty($quotes) ) {
            if ( ! is_array($quotes) ) {
                throw new RuntimeException('$quotes parameter needs to be an array, ' . gettype($quotes) . ' given', EINVAL);
            }
            $regex_quotes = [];
            foreach ($quotes as $quote_delims) {
                if ( ! is_array($quote_delims) || count($quote_delims) != 2 ) {
                    //  This is an awful error message but the best I can do at the moment.
                    throw new RuntimeException('$quotes parameter needs to be an array of array pairs', EINVAL);
                }
                list($open, $close) = [preg_quote($quote_delims[0], '/'), preg_quote($quote_delims[1], '/')];
                $regex_quotes[] = "[$open][^$close]*[$close]";
            }
            $regex_quotes = implode('|', $regex_quotes);
            $quoted_chunks = preg_split("/($regex_quotes)/U", $string, ($limit < 1 ? 0 : $limit), PREG_SPLIT_DELIM_CAPTURE);
        }
        else {
            $quoted_chunks = [$string];
        }
        //  $quoted_chunks is now an array of some number of quote-enclosed
        //  chunks from $string. The 0th array element will be unquoted, the
        //  1st will be quoted, the 2nd will be unquoted, and so on.
        //  Thus every even-indexed array element will be further split by
        //  $delimiters, while the odd-indexed elements will be returned unchanged.
        $regex = '';
        if ( $strip_delimiter ) {
            $flags = PREG_SPLIT_NO_EMPTY;
            if ( is_string($delimiters) ) {
                $regex = '/[' . preg_quote($delimiters, '/') . ']/';
            }
            else if ( is_array($delimiters) ) {
                $delimiters = implode('|', array_map(function($delimiter){
                    return preg_quote($delimiter, '/');
                }, $delimiters));
                $regex = "/($delimiters)/";
            }
        }
        else {
            //  These regular expressions are crafted to include the matching
            //  delimiter at the start of each token.
            $flags = PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY;
            if ( is_string($delimiters) ) {
                $regex = '/([' . preg_quote($delimiters, '/') . '][^' . preg_quote($delimiters, '/') . ']*)/';
            }
            else if ( is_array($delimiters) ) {
                $delimiters = implode('|', array_map(function($delimiter){
                    return preg_quote($delimiter, '/');
                }, $delimiters));
                //  Okay, this right here is some nightmare fuel. If I'm luckier
                //  than I deserve, I'll never have to debug this.
                $regex = "/((?:.(?!$delimiters))*(?:(?=$delimiters).)*.?)/";
            }
        }
        if ( $regex == '' ) {
            throw new RuntimeException('$delimiters parameter needs to be a string or an array, ' . gettype($delimiters) . ' given', EINVAL);
        }
        while ( count($quoted_chunks) && ($limit < 1 || count($chunks) < $limit) ) {
            $unquoted_chunks = preg_split($regex, array_shift($quoted_chunks), 0, $flags);
            if ( count($quoted_chunks) ) {
                //  If there's another quoted chunk remaining, then the last
                //  element of the unquoted chunk should be a delimiter, which
                //  belongs at the beginning of the quoted chunk string.
                $quoted_chunks[0] = implode('', [array_pop($unquoted_chunks), $quoted_chunks[0]]);
            }
            $chunks = array_merge($chunks, $unquoted_chunks, [array_shift($quoted_chunks)]);
        }
        //  Remove any pollution in the output from array_pop() or bad preg_split() results.
        $chunks = array_filter($chunks, 'strlen');
        //  Finally, ensure that the chunk count didn't exceed $limit; this
        //  can happen pretty easily with preg_split().
        $chunks = array_merge($chunks, $quoted_chunks);
        if ( $limit > 0 && count($chunks) > $limit ) {
            $chunks = array_merge(array_splice($chunks, 0, $limit - 1), [implode('', $chunks)]);
        }
        return $chunks;
    }


    /**
     * Return the number of matching characters at the start of $string1 and
     * $string2. Example: str_matchlen('abcdef', 'abcefg') returns 3.
     *
     * @param   string      $string1
     * @param   string      $string2
     *
     * @return  int
     */
    public static function str_matchlen (string $string1, string $string2) : int
    {
        $i = 0;
        $n = min(strlen($string1), strlen($string2));
        while ( $i < $n && $string1[$i] === $string2[$i] ) $i++;
        return $i;
    }


    /**
     * Similar to addslashes(), but idempotent: it only escapes characters in a
     * string if the string hasn't already been fully escaped.
     *
     * @param   string      $string
     * @param   string      $escape_chars
     *
     * @return  string
     */
    public static function escape_str (string $string, string $escape_chars = '\"') : string
    {
        $chunks = static::str_chunk($string, $escape_chars);
        $n = count($chunks);
        for ( $i = 0; $i < $n; $i++ ) {
            if ( $chunks[$i][0] == '\\' ) {
                $i++;
                continue;
            }
            if ( strpos($escape_chars, $chunks[$i][0]) === false ) {
                continue;
            }
            //  If we're here, then the string is not fully escaped and a
            //  backslash must be prepended to each chunk.
            $chunks = array_map(function($chunk){
                return '\\' . $chunk;
            }, $chunks);
            break;
        }
        return implode('', $chunks);
    }


    /**
     * Convert various data types and values to a cleaner string representation
     * than PHP's native var_dump() or print_r() functions. var_dump() is still
     * better if you need to look inside an object; this is better if you want
     * to log some basic stuff to a file or include a value in an error message.
     * 
     * @param   mixed       $thing
     *
     * @return  string
     */
    public static function to_str ($thing) : string
    {
        switch (true) {
            case (is_string($thing)):
                if ( strlen($thing) > 50 ) {
                    $thing = substr($thing, 0, 50) . '...';
                }
                return '"' . static::escape_str($thing) . '"';
            case (is_int($thing)):
                return "int($thing)";
            case (is_float($thing)):
                return "float($thing)";
            case (is_bool($thing)):
                return $thing ? 'bool(true)' : 'bool(false)';
            case (is_null($thing)):
                return 'null';
            case (is_resource($thing)):
                return 'resource(' . get_resource_type($thing) . ')';
            case (is_object($thing)):
                return 'object(' . get_class($thing) . ')';
            case (is_array($thing)):
                if ( static::is_linear_array($thing) ) {
                    $values = array_map(function($element){
                        return Functions::to_str($element);
                    }, $thing);
                }
                else {
                    $values = array_map(function($key, $value){
                        return Functions::to_str($key) . ' => ' . Functions::to_str($value);
                    }, array_keys($thing), array_values($thing));
                }
                return '[' . implode(', ', $values) . ']';
            default:
                return '(' . gettype($thing) . ')';
        }
    }


    /**
     * Convert a UTF-8 string into an array of individual characters.
     *
     * @param   string      $string
     *
     * @return  array
     */
    public static function utf8_str_split (string $string) : array
    {
        return preg_split('//u', $string, null, PREG_SPLIT_NO_EMPTY);
    }


    /**
     * Return true if an array's keys are sequential integers starting at 0,
     * false otherwise.
     *
     * @param   array       $array
     *
     * @return  boolean
     */
    public static function is_linear_array (array $array) : bool
    {
        $keys = array_keys($array);
        return count($keys) == 0 || ($keys[0] == 0 && $keys == range(0, count($keys)-1));
    }


    /**
     * Convert an array from ['a', 'b', 'c'] to ['a' => ['b' => 'c' => []]].
     *
     * @param   array       $array
     *
     * @return  array
     */
    public static function array_nest (array $array) : array
    {
        $nested = [];
        while ( count($array) ) {
            $nested = [array_pop($array) => $nested];
        }
        return $nested;
    }


    /**
     * Return the first element of an array or other value.
     *
     * @param  mixed    $value
     *
     * @return mixed
     */
    public static function first ($value)
    {
        //  https://stackoverflow.com/a/41795859
        //  This is fast enough even for large arrays and doesn't require any
        //  onerous error checking.
        return array_shift($value);
    }


    /**
     * Return the last element of an array or other value.
     *
     * @param  mixed    $value
     *
     * @return mixed
     */
    public static function last ($value)
    {
        //  https://stackoverflow.com/a/41795859
        //  This is fast enough even for large arrays and doesn't require any
        //  onerous error checking.
        return array_pop($value);
    }


    /**
     * Return true if Composer is present.
     *
     * @return  boolean
     */
    public static function have_composer () : bool
    {
        return class_exists('Composer\Autoload\ClassLoader', false);
    }


    /**
     * Return true if a class is available (without loading it).
     *
     * This function performs a little guesswork and will return some false
     * negative results in applications with custom autoloaders.
     *
     * It is identical to class_exists(), except that in common Composer
     * environments, it can search for a classmap without needing to load
     * the class being searched for.
     *
     * @param   string      $classname
     * @param   boolean     $allow_loading
     *
     * @return  boolean
     */
    public static function class_available (string $classname, bool $allow_loading = false) : bool
    {
        if ( class_exists($classname, $allow_loading) ) {
            return true;
        }
        if ( static::have_composer() ) {
            //  Composer may have a classmap available that can be interrogated
            //  without loading the class in question.
            $composer_initer = array_values(preg_grep('/^ComposerAutoloaderInit[0-9a-f]{32}$/', get_declared_classes()));
            if ( count($composer_initer) === 1 ) {
                $composer_initer = array_shift($composer_initer);
                //  This is very naughty (and only works in some common Composer
                //  configurations).
                $class_map = $composer_initer::getLoader()->getClassMap();
                if ( array_key_exists($classname, $class_map) ) {
                    return true;
                }
            }
        }
        return false;
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
    protected static function _caller_is ($reference) : bool
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

trait DatastreamLogging
{
    //  The DatastreamLogging trait provides all of the code that Datastreams
    //  need to store, broadcast, and return errors and other messages.

}


/*******************************************************************************
*                                                                              *
*   Bootstrap.                                                                 *
*   If a Composer autoloader isn't already present, then Asinius will          *
*   install its own autoloader.                                                *
*                                                                              *
*******************************************************************************/

if ( empty(array_filter(spl_autoload_functions(), function($loader){
    return is_callable($loader, false, $loader_name) && strpos($loader_name, 'Composer\\') === 0;
})) ) {
    Asinius::init_autoloader();
}
