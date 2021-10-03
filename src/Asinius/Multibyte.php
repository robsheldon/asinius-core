<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Multibyte                                                          *
*                                                                              *
*   A collection of utility functions for dealing with multibyte headaches.    *
*                                                                              *
*   NOTE: If this class file gets loaded and PHP's native mb_* aren't          *
*   available, it will immediately throw a RuntimeException.                   *
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

namespace Asinius;

use Exception, RuntimeException;

if ( ! function_exists('mb_list_encodings') ) {
    throw new RuntimeException("Multibyte support has been requested by the application but multibyte support is not available in the current PHP runtime. See: https://www.php.net/manual/en/mbstring.setup.php");
}


/*******************************************************************************
*                                                                              *
*   \Asinius\Multibyte                                                         *
*                                                                              *
*******************************************************************************/

class Multibyte
{

    private static $_encodings              = [];
    private static $_native_mb_str_split    = null;


    /**
     * Return true if the requested encoding is supported by this runtime,
     * false otherwise. The complete list of all available encodings is
     * cached internally the first time this function is called.
     *
     * @param   string      $requested_encoding
     *
     * @return  bool
     */
    public static function supported_encoding (string $requested_encoding) : bool
    {
        if ( empty(static::$_encodings) ) {
            //  Load all available encodings and their aliases. Yuck. :-(
            $encodings = mb_list_encodings();
            static::$_encodings = array_flip($encodings);
            foreach ($encodings as $encoding) {
                static::$_encodings = array_merge(static::$_encodings, array_flip(mb_encoding_aliases($encoding)));
            }
        }
        return isset(static::$_encodings[$requested_encoding]);
    }


    /**
     * Simple wrapper for mb_strlen().
     *
     * This helps isolate mb_* IDE warnings to this file, which properly checks
     * for mb_* support when it's first loaded.
     *
     * @param   string      $string
     * @param   ?string     $encoding
     *
     * @return  string
     */
    public static function strlen (string $string, string $encoding = null) : string
    {
        return mb_strlen($string, $encoding);
    }


    /**
     * Simple wrapper for mb_strcut().
     *
     * This helps isolate mb_* IDE warnings to this file, which properly checks
     * for mb_* support when it's first loaded.
     *
     * @param   string      $string
     * @param   int         $start
     * @param   ?int        $length
     * @param   ?string     $encoding
     *
     * @return  string
     */
    public static function strcut (string $string, int $start, int $length = null, string $encoding = null) : string
    {
        return mb_strcut($string, $start, $length, $encoding);
    }


    /**
     * Polyfill for the PHP 7.4-and-later mb_str_split() function. If the
     * native function is available, it will be called instead.
     *
     * @param   string      $subject
     * @param   int         $length
     * @param   null        $encoding
     *
     * @return  array
     *@throws  RuntimeException
     *
     */
    public static function str_split (string $subject, int $length = 1, $encoding = null) : array
    {
        if ( static::$_native_mb_str_split === null ) {
            static::$_native_mb_str_split = function_exists('mb_str_split');
        }
        if ( static::$_native_mb_str_split ) {
            return mb_str_split($subject, $length, $encoding);
        }
        if ( ! is_string($subject) ) {
            if ( is_object($subject) ) {
                try {
                    $subject = "$subject";
                }
                catch (Exception $e) {
                    throw new RuntimeException("mb_str_split(): Can't convert this object into a string");
                }
            }
            throw new RuntimeException('mb_str_split(): parameter 1 (subject) should be a string, not a ' . gettype($subject));
        }
        if ( ! is_int($length) ) {
            throw new RuntimeException('mb_str_split(): parameter 2 (length) should be an int, not a ' . gettype($length));
        }
        if ( $length < 1 ) {
            return [];
        }
        if ( $encoding === null ) {
            $encoding = mb_internal_encoding();
        }
        if ( ! is_string($encoding) ) {
            throw new RuntimeException('mb_str_split(): parameter 3 (encoding) should be a string, not a ' . gettype($encoding));
        }
        if ( ! static::supported_encoding($encoding) ) {
            throw new RuntimeException("mb_str_split(): this encoding is not supported in this environment: $encoding");
        }
        $chunks = [];
        while ( mb_strlen($subject) > 0 ) {
            $chunks[] = mb_substr($subject, 0, $length, $encoding);
            $subject = mb_substr($subject, $length, null, $encoding);
        }
        return $chunks;
    }


}
