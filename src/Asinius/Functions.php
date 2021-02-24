<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Functions                                                          *
*                                                                              *
*   A superclass of lower-level miscellaneous functions -- things that I wish  *
*   were present in the PHP standard library.                                  *
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
*   \Asinius\Functions                                                         *
*                                                                              *
*******************************************************************************/

class Functions
{

    const DEFAULT_QUOTES   = [["'", "'"], ['"', '"']];
    const DEFAULT_PARENS   = [['(', ')']];
    const DEFAULT_BRACKETS = [['[', ']'], ['{', '}']];

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
     * @throws  \RuntimeException
     * 
     * @return  array
     */
    public static function str_chunk ($string, $delimiters, $limit = 0, $quotes = [], $strip_delimiter = false)
    {
        $chunks = [];
        //  Start by decomposing the string into quoted and unquoted chunks.
        //  This is best done by building a yucky regex for preg_split().
        if ( ! empty($quotes) ) {
            if ( ! is_array($quotes) ) {
                throw new \RuntimeException('$quotes parameter needs to be an array, ' . gettype($quotes) . ' given', EINVAL);
            }
            $regex_quotes = [];
            foreach ($quotes as $quote_delims) {
                if ( ! is_array($quote_delims) || count($quote_delims) != 2 ) {
                    //  This is an awful error message but the best I can do at the moment.
                    throw new \RuntimeException('$quotes parameter needs to be an array of array pairs', EINVAL);
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
                $regex = "/({$delimiters})/";
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
                $regex = "/((?:.(?!{$delimiters}))*(?:(?={$delimiters}).)*.?)/";
            }
        }
        if ( $regex == '' ) {
            throw new \RuntimeException('$delimiters parameter needs to be a string or an array, ' . gettype($delimiters) . ' given', EINVAL);
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
            $chunks = array_merge($chunks_out = array_splice($chunks, 0, $limit - 1), [implode('', $chunks)]);
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
    public static function str_matchlen ($string1, $string2)
    {
        $n = min(strlen($string1), strlen($string2));
        for ( $i = 0; $i < $n && $string1[$i] === $string2[$i]; $i++ );
        return $i;
    }


    /**
     * Similar to addslashes(), but idempotent: it only escapes characters in a
     * string if the string hasn't already been fully escaped.
     *
     * @param   string      $string
     * @return  string
     */
    public static function escape_str ($string, $escape_chars = '\"')
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
     * @return  string
     */
    public static function to_str ($thing)
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
                        return \Asinius\Functions::to_str($element);
                    }, $thing);
                }
                else {
                    $values = array_map(function($key, $value){
                        return \Asinius\Functions::to_str($key) . ' => ' . \Asinius\Functions::to_str($value);
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
    public static function utf8_str_split ($string)
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
    public static function is_linear_array ($array)
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
    public static function array_nest ($array)
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
        return count($value) == 0 ? null : $value[0];
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
        return (($n = count($value)) == 0) ? null : $value[$n - 1];
    }

}
