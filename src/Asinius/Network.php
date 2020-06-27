<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Network                                                            *
*                                                                              *
*   Network diagnostic, troubleshooting, and other utility functions used by   *
*   other components.                                                          *
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
*   \Asinius\Network                                                           *
*                                                                              *
*******************************************************************************/

class Network
{

    private static $_can_ping       = null;
    private static $_can_timeout    = null;
    private static $_host_os        = null;


    /**
     * Attempt to send an ICMP request to an address. Returns true if successful,
     * false if there was no ping response within the timeout period, and null
     * if no ping method was available.
     *
     * @param   string      $address
     * @param   float       $max_timeout
     *
     * @internal
     *
     * @return  mixed
     */
    private static function _test_icmp ($address, $max_timeout)
    {
        if ( \Asinius\Environment::can_exec() ) {
            switch (\Asinius\Environment::os_type()) {
                case 'Windows':
                    $ping_cmd = sprintf('ping -n 1 -w %d %s', $max_timeout * 1000, $address);
                    break;
                case 'MacOS':
                    $ping_cmd = sprintf('ping -n -c 1 -t %s %s', $max_timeout, $address);
                    break;
                case 'Linux':
                    $ping_cmd = '';
                    //  See if the timeout command is available.
                    if ( ! \Asinius\Environment::defined('can_timeout') ) {
                        \Asinius\Environment::add_property('can_timeout', ! empty(exec('which timeout')));
                    }
                    if ( \Asinius\Environment::can_timeout() ) {
                        $ping_cmd .= sprintf('timeout %0.3g ', $max_timeout);
                    }
                    $ping_cmd .= sprintf('ping -n -c 1 -w %d %s 2>&1', ceil($max_timeout), $address);
                    break;
                default:
                    return null;
            }
            exec($ping_cmd, $output);
            //  Search the output for something resembling either "0% packet loss"
            //  or "100% packet loss".
            $pattern = '/(?P<pct>\d+)% (packet )?loss/';
            $matched = preg_match($pattern, implode(' ', $output), $matches);
            if ( $matched === 0 || empty($matches) ) {
                //  This has to be counted as a failure because the ping command
                //  may have timed out before the packet returned.
                return false;
            }
            return $matches['pct'] != '100';
        }
        //  There are currently no other methods that I know of that don't require
        //  root privileges, and PHP should never ever ever ever run as root.
        return null;
    }


    /**
     * Try to open a tcp connection to a specific address/port within $max_timeout
     * seconds.
     *
     * @param   string      $address
     * @param   int         $port
     * @param   float       $max_timeout
     *
     * @internal
     * 
     * @return  boolean
     */
    private static function _test_tcp ($address, $port, $max_timeout)
    {
        $socket = @fsockopen($address, $port, $error_number, $error_message, $max_timeout);
        if ( $socket === false ) {
            return false;
        }
        @fclose($socket);
        return true;
    }


    /**
     * Try reaching one or more hosts on some specified ports and return the
     * results in a simple array.
     *
     * Hosts should be specified in an "address:port" format. Use "icmp" for
     * the port to send a ping, otherwise numeric values will attempt a tcp
     * connection on that port.
     *
     * Results are collated so if you want to test multiple hosts on multiple
     * ports you may want to loop over them.
     *
     * @param   array       $addresses
     * @param   float       $max_timeout
     *
     * @return  array
     */
    public static function try_hosts ($hosts, $max_timeout = .25)
    {
        $results = ['hosts' => [], 'ports' => []];
        foreach ($hosts as $host) {
            $matched = preg_match('/^(?P<host>[a-z0-9\.-]+):(?P<port>([0-9]+)|(icmp))/', strtolower($host), $matches);
            if ( $matched !== 1 || empty($matches) ) {
                throw new \RuntimeException("Invalid host: $host");
            }
            list($address, $port) = [$matches['host'], $matches['port']];
            if ( ! array_key_exists($address, $results['hosts']) ) {
                $results['hosts'][$address] = ['success' => 0, 'fail' => 0];
            }
            if ( ! array_key_exists($port, $results['ports']) ) {
                $results['ports'][$port] = ['success' => 0, 'fail' => 0];
            }
            if ( $port === 'icmp' ) {
                $result = self::_test_icmp($address, $max_timeout);
            }
            else {
                $result = self::_test_tcp($address, $port, $max_timeout);
            }
            //  $result may also have a "null" value if the test could not be
            //  run for some reason.
            if ( $result === true ) {
                $results['hosts'][$address]['success']++;
                $results['ports'][$port]['success']++;
            }
            else if ( $result === false ) {
                $results['hosts'][$address]['fail']++;
                $results['ports'][$port]['fail']++;
            }
        }
        return $results;
    }


    /**
     * Test the status of the network and return a simple array of the results.
     * WARNING: This function may take several seconds to complete if the network
     * connection is badly degraded.
     *
     * @param   float       $max_timeout
     *
     * @return  array
     */
    public static function test ($max_timeout = .25)
    {
        $tests = [
            //  Address, network, port
            ['8.8.8.8', 'Google', 'icmp'],
            ['8.8.4.4', 'Google', 'icmp'],
            ['1.1.1.1', 'Cloudflare', 'icmp'],
            ['8.8.8.8', 'Google', '443'],
            ['8.8.4.4', 'Google', '443'],
            ['1.1.1.1', 'Cloudflare', '443'],
            ['1.1.1.1',  'Cloudflare', '80'],
            ['google.com', 'Google', '443'],
            ['google.com', 'Google',  '80'],
            ['one.one.one.one', 'Cloudflare', '443'],
            ['one.one.one.one', 'Cloudflare',  '80'],
        ];
        $results = [
            'networks'      => [],
            'hosts'         => [],
            'ports'         => [],
            'all_dns'       => ['success' => 0, 'fail' => 0],
            'all_tcp'       => ['success' => 0, 'fail' => 0],
            'all'           => ['success' => 0, 'fail' => 0],
            'status'        => '',
            'message'       => '',
        ];
        //  Run all the tests and compile the results.
        foreach ($tests as $test) {
            list($address, $network, $port) = $test;
            if ( ! array_key_exists($network, $results['networks']) ) {
                $results['networks'][$network] = ['success' => 0, 'fail' => 0];
            }
            if ( ! array_key_exists($address, $results['hosts']) ) {
                $results['hosts'][$address] = ['success' => 0, 'fail' => 0];
            }
            if ( ! array_key_exists($port, $results['ports']) ) {
                $results['ports'][$port] = ['success' => 0, 'fail' => 0];
            }
            $test_result = self::try_hosts([sprintf('%s:%s', $address, $port)], $max_timeout);
            foreach (['success', 'fail'] as $count) {
                $results['networks'][$network][$count]  += $test_result['hosts'][$address][$count];
                $results['hosts'][$address][$count]     += $test_result['hosts'][$address][$count];
                $results['ports'][$port][$count]        += $test_result['ports'][$port][$count];
                if ( $port != 'icmp' ) {
                    $results['all_tcp'][$count] += $test_result['ports'][$port][$count];
                }
                if ( preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $address) !== 1 ) {
                    $results['all_dns'][$count] += $test_result['hosts'][$address][$count];
                }
                $results['all'][$count]         += $test_result['hosts'][$address][$count];
            }
        }
        //  Interpret the results.
        if ( $results['all']['success'] > 0 && $results['all']['fail'] == 0 ) {
            //  https://www.youtube.com/watch?v=9cQgQIMlwWw
            $results['status'] = 'ok';
            $results['message'] = 'The network appears to be working correctly.';
        }
        else if ( $results['ports']['icmp']['success'] == 0 && $results['all_tcp']['fail'] == 0 ) {
            //  Eveyrthing is awesome except ICMP.
            //  Consider this to be an "okay" result since it's not uncommon
            //  for icmp to be blocked in lame network configurations.
            $results['status'] = 'ok';
            $results['message'] = 'ICMP is blocked but the rest of the network is working correctly.';
        }
        else if ( $results['all']['success'] == 0 && $results['all']['fail'] > 0 ) {
            $results['status'] = 'offline';
            $results['message'] = 'The network interface appears to be completely offline.';
        }
        else {
            $results['status'] = 'degraded';
            //  Is it just one network that's unreachable?
            while (true) {
                //  Is it only DNS that's down?
                if ( $results['all_dns']['success'] == 0 && $results['all_dns']['fail'] > 0 && $results['all']['fail'] == $results['all_dns']['fail'] ) {
                    $results['message'] = 'DNS appears to be unavailable but other network connections are working okay.';
                    break;
                }
                foreach (['hosts', 'ports', 'networks'] as $type) {
                    //  Check to see if it's just one of any of these that's down.
                    $downs = [];
                    foreach ($results[$type] as $thing => $tests) {
                        if ( $tests['success'] == 0 && $tests['fail'] > 0 ) {
                            $downs[] = $thing;
                        }
                    }
                    if ( count($downs) == 1 && $results[$type][$downs[0]]['fail'] == $results['all']['fail'] ) {
                        $thing = $downs[0];
                        switch ($type) {
                            case 'hosts':
                                $results['message'] = "Host $thing is unreachable but everything else is working okay.";
                                break;
                            case 'ports':
                                $results['message'] = "TCP port $thing is unreachable but everything else is working okay.";
                                break;
                            case 'networks':
                                $results['message'] = "$thing's entire network is unreachable but everything else is working okay.";
                                break;
                        }
                        break;
                    }
                }
                if ( ! empty($results['message']) ) {
                    //  A single failure point was identified.
                    break;
                }
                //  Set a default message.
                $results['message'] = 'The network appears to be suffering from congestion or intermittent failures across multiple networks and ports.';
                break;
            }
        }
        return $results;
    }

}
