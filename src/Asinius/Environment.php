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
*   Copyright (c) 2023 Rob Sheldon <rob@robsheldon.com>                        *
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

class Environment
{

    //  Default properties that can be set by functions in this class.
    private static array $_properties = [
        'os_type'               => null,
        'can_exec'              => null,
        'can_shell_exec'        => null,
        'safe_mode'             => null,
        'disabled_functions'    => null,
        'context'               => null,
    ];


    /**
     * Allow applications to define and call static functions in this class.
     *
     * @param   string      $function
     * @param   array       $arguments
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public static function __callStatic (string $function, array $arguments)
    {
        if ( ! is_null(self::$_properties[$function]) ) {
            return self::$_properties[$function];
        }
        throw new RuntimeException("Undefined environment property: $function");
    }


    /**
     * Return the operating system type, one of 'Windows', 'Linux', or 'MacOS'.
     *
     * @return string
     */
    public static function os_type (): string
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        switch (strtolower(PHP_OS)) {
            case 'windows':
            case 'win32':
            case 'winnt':
                return self::$_properties[__FUNCTION__] = 'Windows';
            case 'linux':
                return self::$_properties[__FUNCTION__] = 'Linux';
            case 'darwin':
                return self::$_properties[__FUNCTION__] = 'MacOS';
            default:
                return self::$_properties[__FUNCTION__] = PHP_OS;
        }
    }


    /**
     * Return true if the 'exec()' function is available and working as expected,
     * false otherwise.
     *
     * @return bool
     */
    public static function can_exec (): bool
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        try {
            self::$_properties[__FUNCTION__] = ! self::safe_mode() && function_exists('exec') && ! in_array('exec', self::disabled_functions()) && trim(exec('echo EXEC')) === 'EXEC';
        }
        catch (Exception $e) {
            self::$_properties[__FUNCTION__] = false;
        }
        return self::$_properties[__FUNCTION__];
    }


    /**
     * Return true if the'shell_exec()' function is available and working as expected,
     * false otherwise.
     *
     * @return bool
     */
    public static function can_shell_exec (): bool
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        try {
            self::$_properties[__FUNCTION__] = ! self::safe_mode() && function_exists('shell_exec') && ! in_array('shell_exec', self::disabled_functions()) && trim(shell_exec('echo SHELL_EXEC')) == 'SHELL_EXEC';
        }
        catch (Exception $e) {
            self::$_properties[__FUNCTION__] = false;
        }
        return self::$_properties[__FUNCTION__];
    }


    /**
     * Return true if 'safe_mode' is set in php.ini, false otherwise.
     *
     * @return bool
     */
    public static function safe_mode (): bool
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        return self::$_properties[__FUNCTION__] = in_array(strtolower(ini_get('safe_mode')), ['on', '1'], true);
    }


    /**
     * Return a list of functions that are disabled in php.ini.
     *
     * @return array
     */
    public static function disabled_functions (): array
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        return self::$_properties[__FUNCTION__] = array_filter(array_map('trim', explode(',', ini_get('disable_functions'))), function($element){
            return ! is_string($element) || strlen($element) > 0;
        });
    }


    /**
     * Return the current execution context, one of 'cli', 'web', or 'cli-interactive'.
     *
     * PHP doesn't have an official, foolproof way to determine whether an
     * application is running in a CLI or web context, so this function uses
     * some hueristics to make an educated guess.
     *
     * @return string
     */
    public static function context (): string
    {
        if ( ! is_null(self::$_properties[__FUNCTION__]) ) {
            return self::$_properties[__FUNCTION__];
        }
        $contexts = ['cli' => 0, 'web' => 0, 'cli-interactive' => 0];
        //  PHP_SAPI tests should -only- contain values which are certain
        //  to be correct in all environments. "php-cgi" for example is
        //  reported to occur in both web requests and cron job invocations
        //  in some environments, so further detection efforts are required.
        if ( PHP_SAPI === 'cli' ) {
            //  It would be nice to do an additional check here, like:
            //      (posix_getuid() > 999 || posix_getuid() === 0)
            //  ...but this is not universal enough across different
            //  operating systems.
            $contexts['cli']++;
        }
        //  $_SERVER values may be set in some CLI environments, for unit
        //  testing for example, so we really need a PHP_SAPI match too.
        if ( in_array(PHP_SAPI, ['apache', 'cgi', 'cgi-fcgi', 'cli-server', 'fpm-fcgi']) && (isset($_SERVER['HTTP_USER_AGENT']) || isset($_SERVER['REQUEST_METHOD'])) ) {
            $contexts['web'] += 2;
        }
        if ( (empty($_SERVER['REMOTE_ADDR']) && count($_SERVER['argv']) > 0) || isset($_ENV['SHELL']) ) {
            $contexts['cli']++;
        }
        //  The STDIN test is fairly reliable. 'php://input' is used in
        //  web execution contexts instead, and it should be uncommon for
        //  web applications to mock it.
        if ( defined('STDIN') ) {
            $contexts['cli']++;
        }
        //  Determine if this is an interactive cli or no.
        if ( $contexts['cli'] > 0 && defined('STDOUT') && function_exists('posix_isatty') && posix_isatty(STDOUT) ) {
            $contexts['cli-interactive'] = $contexts['cli'] + 1;
        }
        arsort($contexts, SORT_NUMERIC);
        return self::$_properties[__FUNCTION__] = key($contexts);
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
    public static function add_property (string $property, $value): bool
    {
        if ( ! array_key_exists($property, self::$_properties) && ! is_null($value) ) {
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
    public static function defined (string $property): bool
    {
        return array_key_exists($property, self::$_properties);
    }


    /**
     * Returns a simple array of all currently available environmental properties
     * with each of their values filled in. This function may get expensive in
     * the future and should be used only for debugging purposes.
     *
     * @return  array
     */
    public static function all_properties (): array
    {
        $values = [];
        foreach (self::$_properties as $key => $value) {
            $values[$key] = self::{$key}();
        }
        return $values;
    }
}
