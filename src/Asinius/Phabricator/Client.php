<?php

/*******************************************************************************
*                                                                              *
*   Asinius\Phabricator\Client                                                 *
*                                                                              *
*   Client class for working with Conduit, the Phabricator API.                *
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

namespace Asinius\Phabricator;

/*******************************************************************************
*                                                                              *
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

//  Error codes familiar to C programmers.
//  Invalid function argument
defined('EINVAL')   or define('EINVAL', 22);

//  Custom error conditions.
const _ERR_PHABRICATOR_NOAUTH_          = 'Access denied while communicating with Phabricator';
const _ERR_PHABRICATOR_GENERIC_         = 'There was an unhandled error while communicating with Phabricator';


/*******************************************************************************
*                                                                              *
*   \Asinius\Phabricator\Client                                                *
*                                                                              *
*******************************************************************************/

class Client
{

    private $_http      = null;
    private $_url       = '';
    private $_api_key   = '';

    public function __construct ($url, $api_key)
    {
        $this->_url = rtrim($url, '/') . '/';
        $this->_http = new \Asinius\HTTP\Client();
        $this->_api_key = $api_key;
        //  Test the connection and make sure everything's working now.
        $this->_call_api('POST', 'user.whoami');
    }


    private function _call_api ($method, $path, $parameters = [])
    {
        if ( is_string($parameters) ) {
            parse_str($parameters, $parameters);
        }
        if ( ! is_array($parameters) ) {
            throw new \RuntimeException('Unsupported parameter type: ' . gettype($parameters), EINVAL);
        }
        $parameters['api.token'] = $this->_api_key;
        switch ($method) {
            case 'GET':
                $response = $this->_http->get($this->_url . $path, $parameters);
                break;
            case 'POST':
                $response = $this->_http->post($this->_url . $path, $parameters);
                break;
            default:
                throw new \RuntimeException("Unsupported API request method: $method", EINVAL);
                break;
        }
        switch ($response->code) {
            case 401:
                //  Unauthorized.
                throw new \RuntimeException(_ERR_PHABRICATOR_NOAUTH_);
        }
        if ( $response->content_type == 'application/json' ) {
            $response_json = $response->body;
            if ( ! empty($response_json['error_code']) ) {
                if ( ! empty($response_json['error_info']) ) {
                    throw new \RuntimeException($response_json['error_info']);
                }
                throw new \RuntimeException(_ERR_PHABRICATOR_GENERIC_);
            }
            return $response_json['result'];
        }
        else {
            return $response->body;
        }
        return $response;
    }


    private function _fetch_all ($api_method, $api_path, $api_parameters)
    {
        $results = [];
        $last_cursor = 0;
        while ( true ) {
            $response = $this->_call_api($api_method, $api_path, $api_parameters);
            if ( is_string($response) ) {
                throw new \RuntimeException("Unexpected API response: $response");
            }
            if ( array_key_exists('data', $response) ) {
                $results = array_merge($results, $response['data']);
            }
            if ( array_key_exists('object', $response) ) {
                if ( ! array_key_exists('objects', $results) ) {
                    $results['objects'] = [];
                }
                $object = $response['object'];
                if ( array_key_exists('transactions', $response) ) {
                    $object['transactions'] = $response['transactions'];
                }
                $results['objects'][] = $object;
            }
            if ( array_key_exists('cursor', $response) && ! is_null($response['cursor']['after']) && $response['cursor']['after'] != $last_cursor ) {
                $api_parameters['after'] = $response['cursor']['after'];
            }
            else {
                break;
            }
        }
        return $results;
    }


    private function _generate ($class, $results)
    {
        $objects = [];
        foreach ($results as $result) {
            $objects = new $class($result, $this);
        }
        return $objects;
    }


    public function tasks ($parameters = ['queryKey' => 'all'])
    {
        if ( ! array_key_exists('attachments', $parameters) ) {
            $parameters['attachments'] = array('projects' => true);
        }
        return $this->_generate('Task', $this->_fetch_all('POST', 'maniphest.search', $parameters));
    }


    public function transactions ($parameters = ['queryKey' => 'all'])
    {
        //  example $phid: PHID-TASK-crsxxcbunakc5qy6fpjf
        return $this->_fetch_all('POST', 'transaction.search', $parameters);
    }


    public function users ($parameters = ['queryKey' => 'all'])
    {
        return $this->_fetch_all('POST', 'user.search', $parameters);
    }


    public function projects ($parameters = ['queryKey' => 'all'])
    {
        return $this->_generate('Project', $this->_fetch_all('POST', 'project.search', $parameters));
    }


    public function commits ($parameters = ['queryKey' => 'all'])
    {
        if ( array_key_exists('task_phids', $parameters) ) {
            //  Perform an "edge" search to retrieve commit PHIDs associated
            //  with specific tasks.
            $task_phids = $parameters['task_phids'];
            if ( ! is_array($task_phids) ) {
                $task_phids = [$task_phids];
            }
            unset($parameters['task_phids']);
            $commit_phids = array();
            $matches = $this->_fetch_all('POST', 'edge.search', ['types' => ['task.commit'], 'sourcePHIDs' => $task_phids]);
            if ( empty($matches) ) {
                //  There are no commits for these tasks.
                return [];
            }
            foreach ($matches as $match) {
                $commit_phids[] = $match['destinationPHID'];
            }
            if ( ! array_key_exists('constraints', $parameters) ) {
                $parameters['constraints'] = array();
            }
            if ( empty($parameters['constraints']['phids']) || ! is_array($parameters['constraints']['phids']) ) {
                $parameters['constraints']['phids'] = $commit_phids;
            }
            else {
                $parameters['constraints']['phids'] = array_unique(array_merge($parameters['constraints']['phids'], $commit_phids));
            }
        }
        return $this->_generate('Commit', $this->_fetch_all('POST', 'diffusion.commit.search', $parameters));
    }


    public function create_task ($properties)
    {
        $transactions = array();
        $projects = array();
        foreach ($properties as $property => $value) {
            switch ($property) {
                case 'title':
                case 'description':
                case 'status':
                case 'owner':
                    $transactions[] = ['type' => $property, 'value' => $value];
                    break;
                case 'projects':
                    $projects = array_unique(array_merge($projects, $value));
                    break;
                case 'comments':
                    foreach ($value as $comment) {
                        $transactions[] = ['type' => 'comment', 'value' => $comment];
                    }
                    break;
            }
        }
        if ( ! empty($projects) ) {
            $transactions[] = array('type' => 'projects.set', 'value' => $projects);
        }
        return $this->_fetch_all('POST', 'maniphest.edit', ['transactions' => $transactions]);
    }

}
