<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Environment                                                        *
*                                                                              *
*   Provides a lazy-loaded index of useful information about the operating     *
*   system environment.                                                        *
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
*   \Asinius\Environment                                                       *
*                                                                              *
*******************************************************************************/

class Environment
{

    private static $_properties = [
        'os_type'               => null,
        'can_exec'              => null,
        'can_shell_exec'        => null,
        'safe_mode'             => null,
        'disabled_functions'    => null,
        'context'               => null,
    ];


    /**
     * Provide on-demand access to defined properties. This is structured like
     * this (instead of standard, traditional public static functions) so that
     * requests for specific values can be automatically memoized and all
     * values can easily be returned for debugging purposes in all_properties().
     *
     * @param   string      $function
     * @param   array       $arguments
     *
     * @internal
     * 
     * @return  mixed
     */
    public static function __callStatic ($function, $arguments)
    {
        if ( ! array_key_exists($function, self::$_properties) ) {
            throw new \RuntimeException("Undefined environment property: $function");
        }
        if ( ! is_null(self::$_properties[$function]) ) {
            return self::$_properties[$function];
        }
        switch ($function) {
            case 'os_type':
                switch (strtolower(PHP_OS)) {
                    case 'windows':
                    case 'win32':
                    case 'winnt':
                        return self::$_properties['ostype'] = 'Windows';
                    case 'linux':
                        return self::$_properties['ostype'] = 'Linux';
                    case 'darwin':
                        return self::$_properties['ostype'] = 'MacOS';
                    default:
                        return self::$_properties['ostype'] = PHP_OS;
                }
            case 'can_exec':
                return self::$_properties['can_exec'] = ! self::safe_mode() && function_exists('exec') && ! in_array('exec', self::disabled_functions()) && trim(exec('echo EXEC')) == 'EXEC';
            case 'can_shell_exec':
                return self::$_properties['can_shell_exec'] = ! self::safe_mode() && function_exists('shell_exec') && ! in_array('shell_exec', self::disabled_functions()) && trim(shell_exec('echo SHELL_EXEC')) == 'SHELL_EXEC';
            case 'safe_mode':
                return self::$_properties['safe_mode'] = in_array(strtolower(ini_get('safe_mode')), ['on', '1'], true);
            case 'disabled_functions':
                return self::$_properties['disabled_functions'] = array_filter(array_map('trim', explode(',', ini_get('disable_functions'))), function($element){
                    return ! is_string($element) || strlen($element) > 0;
                });
            case 'context':
                //  $cli and $web should -only- contain answers which are certain
                //  to be correct in all environments. "php-cgi" for example is
                //  reported to occur in both web requests and cron job invocations
                //  in some environments, so further detection efforts are required.
                $cli = ['cli'];
                $web = ['apache', 'cgi', 'cgi-fcgi', 'cli-server', 'fpm-fcgi'];
                $context = 'unknown';
                if ( in_array(PHP_SAPI, $cli) ) {
                    //  It would be nice to do an additional check here, like:
                    //      (posix_getuid() > 999 || posix_getuid() === 0)
                    //  ...but this is not universal enough across different
                    //  operating systems.
                    $context = 'cli';
                }
                else if ( in_array(PHP_SAPI, $web) || isset($_SERVER['HTTP_USER_AGENT']) || isset($_SERVER['REQUEST_METHOD']) ) {
                    $context = 'web';
                }
                else if ( (empty($_SERVER['REMOTE_ADDR']) && count($_SERVER['argv']) > 0) || isset($_ENV['SHELL']) ) {
                    $context = 'cli';
                }
                if ( $context == 'cli' ) {
                    //  Determine if this is an interactive cli or no.
                    if ( defined('STDOUT') && posix_isatty(STDOUT) ) {
                        $context = 'cli-interactive';
                    }
                }
                return self::$_properties['context'] = $context;
        }
    }


    /**
     * Add a property and non-null value to the environment. Used by other
     * components to cache environmental information. Returns true if the
     * property was successfully saved, false if it already existed or had
     * a null value.
     *
     * @param   string      $property
     * @param   mixed       $value
     *
     * @return  boolean
     */
    public static function add_property ($property, $value)
    {
        if ( is_string($property) && ! array_key_exists($property, self::$_properties) && ! is_null($value) ) {
            self::$_properties[$property] = $value;
            return true;
        }
        return false;
    }


    /**
     * Returns true if a property is defined, false otherwise.
     *
     * @param   string      $property
     *
     * @return  boolean
     */
    public static function defined ($property)
    {
        return array_key_exists($property, self::$_properties);
    }


    /**
     * Returns a simple array of all of the currently available environmental
     * properties with each of their values filled in. This function may get
     * expensive in the future and should be used only for debugging purposes.
     *
     * @return  array
     */
    public static function all_properties ()
    {
        $values = [];
        foreach (self::$_properties as $key => $value) {
            $values[$key] = self::{$key}();
        }
        return $values;
    }
}
