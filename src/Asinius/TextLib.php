<?php

/*******************************************************************************
*                                                                              *
*   Asinius\TextLib                                                            *
*                                                                              *
*   A superclass of lower-level text juggling functions -- things that I wish  *
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
*   \Asinius\TextLib                                                           *
*                                                                              *
*******************************************************************************/

class TextLib
{

    const DEFAULT_QUOTES = [["'", "'"], ['"', '"']];

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
     * Each chunk will begin with the delimiter it was split on.
     * 
     * @param   string      $string
     * @param   mixed       $delimiters
     * @param   integer     $limit
     * @param   array       $quotes
     *
     * @throws  \RuntimeException
     * 
     * @return  array
     */
    public static function str_chunk ($string, $delimiters, $limit = 0, $quotes = [])
    {
        $chunks = [];
        //  Start by decomposing the string into quoted and unquoted chunks.
        //  This is best done by building a yucky regex for preg_split().
        if ( ! empty($quotes) ) {
            $regex_quotes = [];
            if ( ! is_array($quotes) ) {
                throw new \RuntimeException("\$quotes parameter needs to be an array, " . gettype($quotes) . " given.");
            }
            foreach ($quotes as $quote_delims) {
                if ( ! is_array($quote_delims) || count($quote_delims) != 2 ) {
                    //  This is an awful error message but the best I can do at the moment.
                    throw new \RuntimeException("\$quotes parameter needs to be an array of array pairs.");
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
        //  1th will be quoted, the 2th will be unquoted, and so on.
        //  Thus every even-indexed array element will be further split by
        //  $delimiters, while the odd-indexed elements will be returned unchanged.
        if ( is_string($delimiters) ) {
            $regex = '[' . preg_quote($delimiters, '/') . '][^' . preg_quote($delimiters, '/') . ']+';
        }
        else if ( is_array($delimiters) ) {
            $delimiters = implode('|', array_map(function($delimiter){
                return preg_quote($delimiter, '/');
            }, $delimiters));
            //  Okay, this right here is some nightmare fuel. If I'm luckier
            //  than I deserve, I'll never have to debug this.
            $regex = "(?:.(?!{$delimiters}))*(?:(?={$delimiters}).)*.?";
        }
        else {
            throw new \RuntimeException("\$delimiters parameter needs to be a string or an array, " . gettype($delimiters) . " given.");
        }
        while ( count($quoted_chunks) && ($limit < 1 || count($chunks) < $limit) ) {
            $unquoted_chunks = preg_split("/($regex)/", array_shift($quoted_chunks), 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if ( count($quoted_chunks) ) {
                //  If there's another quoted chunk remaining, then the last
                //  element of the unquoted chunk should be a delimiter, which
                //  belongs at the beginning of the quoted chunk string.
                $quoted_chunks[0] = implode('', [array_pop($unquoted_chunks), $quoted_chunks[0]]);
            }
            $chunks = array_merge($chunks, $unquoted_chunks, [array_shift($quoted_chunks)]);
        }
        //  Remove any pollution in the output from array_pop() or bad preg_split() results.
        $chunks = array_filter($chunks, function($chunk){
            return is_string($chunk) && strlen($chunk) > 0;
        });
        //  Finally, ensure that the chunk count didn't exceed $limit; this
        //  can happen pretty easily with preg_split().
        $chunks = array_merge($chunks, $quoted_chunks);
        if ( $limit > 0 && count($chunks) > $limit ) {
            $chunks = array_merge($chunks_out = array_splice($chunks, 0, $limit - 1), [implode('', $chunks)]);
        }
        return $chunks;
    }

}
