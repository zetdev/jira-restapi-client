<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## BaseClient class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Client
 * @since 1.0
 */

namespace Jira\Api\Client;

use Jira\Api\Authentication\Anonymous;
use Jira\Api\Authentication\Basic;
use Jira\Exception\Exception;
use Jira\Exception\InvalidArgumentException;

class BaseClient implements ClientInterface
{
    const METHOD_GET     = 'get';
    const METHOD_POST    = 'post';
    const DATA_TYPE_JSON = 'json';
    
    protected $_auth;
    protected $_client;
    
    protected $_restApiUri = 'rest/api/2';
    protected $_token;
    protected $_apiUrl;
    
    public function __construct($config)
    {
        if (!empty($config['user_id']) && !empty($config['password'])) {
            $this->_auth = new Basic($config['base_url'], $config['user_id'], $config['password']);
        } else {
            $this->_auth = new Anonymous($config['base_url']);
        }

        $this->_apiUrl = $config['base_url'].$this->_restApiUri;
    }
    
    public function getToken()
    {
        if (empty($this->_token)) {
            list($user_id, $password) = $this->_auth->getCredentials();
        
            $this->_token = base64_encode($user_id . ':' . $password);
        }
        return $this->_token;
    }
    
    public function getIssue($issue_id)
    {
        $url = $this->_apiUrl.'/issue/'.$issue_id;
        
        return $this->sendRequest($url, null, self::METHOD_GET);
    }

    public function createIssue(array $params)
    {
        $url = $this->_apiUrl.'/issue/';
        
        return $this->sendRequest($url, $params, self::METHOD_POST);
    }
    
    
    protected function getClient()
    {
        $this->_client = $this->_auth->getClient();
        
        return $this->_client;
    }
    
    /**
     * Send request to the API server
     *
     * @param string $url
     * @param mixed $data
     * @param string $data_type
     * @param string $method
     * @return array|string
     * @throws Exception
     */
    protected function sendRequest($url, $data=null, $method=null, $data_type=null)
    {
        if (empty($this->_client)) {
            $this->_client = $this->getClient();
        }
        
        if (empty($url)) {
            throw new InvalidArgumentException("Request Url must be defined");
        }
        
        if (!$method) {
            $method = self::METHOD_POST;
        }
        if (!$data_type) {
            $data_type = self::DATA_TYPE_JSON;
        }
        
        switch ($data_type) {
            case self::DATA_TYPE_JSON :
                if (!is_string($data)) {
                    $data = json_encode($data);
                }
                if ($method == self::METHOD_POST) {
                    $request = $this->_client->post($url,
                            array('Content-type' => 'application/json'), $data);
                } else {
                    $request = $this->_client->get($url,
                            array('Content-type' => 'application/json'));
                }
            break;
        }
        
        if (isset($request)) {
            $response = $request->send();
            
            if ($response->getStatusCode() == 401)
            {
                throw new Exception("Access allowed only for registered users.");
            }
            elseif ($response->isSuccessful()) {
                return $response->json(); // as array
            } else {
                throw new Exception("Bad Request. Response reason: ".$response->getReasonPhrase());
            }
        }
        
        return false;
    }

}
