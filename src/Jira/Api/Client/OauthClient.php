<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## OauthClient class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Client
 * @since 1.0
 */

namespace Jira\Api\Client;

use Jira\Api\Authentication\Oauth;
use Jira\Exception\Exception;
use Jira\Exception\InvalidArgumentException;

class OauthClient implements ClientInterface
{
    const METHOD_GET     = 'get';
    const METHOD_POST    = 'post';
    const DATA_TYPE_JSON = 'json';
    
    protected $_auth;
    protected $_client;
    
    protected $_token;
    protected $_automaticRevalidateUser = true;

    protected $_requestTokenUri  = 'plugins/servlet/oauth/request-token';
    protected $_authorizationUri = 'plugins/servlet/oauth/authorize?oauth_token=%s';
    protected $_accessTokenUri   = 'plugins/servlet/oauth/access-token';
    protected $_restApiUri       = 'rest/api/2';

    protected $_apiUrl;
    protected $_callbackUrl;
    
    public function __construct($config)
    {
        $this->_auth = new Oauth($config['base_url']);
        $this->_auth->setPrivateKey($config['private_key'])
          ->setConsumerKey($config['consumer_key'])
          ->setConsumerSecret($config['consumer_secret'])
          ->setRequestTokenUrl($this->_requestTokenUri)
          ->setAuthorizationUrl($this->_authorizationUri)
          ->setAccessTokenUrl($this->_accessTokenUri)
          ->setCallbackUrl($config['callback_url']);

        $this->_apiUrl = $config['base_url'].$this->restApiUri;
        $this->_callbackUrl = $config['callback_url'];
    }

    public function getToken()
    {
        if (session_status() != 2) { session_start(); }

        if (empty($this->_token)) {
            if (!isset($_SESSION['JIRA_ACCESS_TOKEN']) && isset($_SESSION['JIRA_REQUEST_TOKEN'])) {
                $this->getAccessToken();
            }
            elseif (!isset($_SESSION['JIRA_ACCESS_TOKEN'])) {
                $this->validateOAuthAccess();
            }

            $this->_token = unserialize($_SESSION['JIRA_ACCESS_TOKEN']);
        }
        return $this->_token;
    }
    
    public function getIssue($issue_id) {
        $url = $this->_apiUrl.'/issue/'.$issue_id;
        
        return $this->sendRequest($url, null, self::METHOD_GET);
    }

    public function createIssue(array $params) {
        $url = $this->_apiUrl.'/issue/';
        
        return $this->sendRequest($url, $params, self::METHOD_POST);
    }
    
    protected function validateOAuthAccess()
    {
        $token = $this->_auth->requestTempCredentials();
        
        $_SESSION['JIRA_REQUEST_TOKEN'] = serialize($token);

        $redirect = $this->_auth->makeAuthUrl();
        $this->redirect($redirect);
    }
    
    protected function getAccessToken($redirect=true)
    {
        $verifier = isset($_REQUEST['oauth_verifier']) ? $_REQUEST['oauth_verifier'] : '';

        if (empty($verifier)) {
            throw new InvalidArgumentException("There was no oauth verifier in the request");
        }

        if (isset($_SESSION['JIRA_REQUEST_TOKEN']))
        {
            $tempToken = unserialize($_SESSION['JIRA_REQUEST_TOKEN']);

            $this->_token = $this->_auth->requestAuthCredentials(
                $tempToken['oauth_token'],
                $tempToken['oauth_token_secret'],
                $verifier
            );
            $_SESSION['JIRA_ACCESS_TOKEN'] = serialize($this->_token);
        }
        else {
            die('Bad Request Token');
        }
        
        if ($redirect) {
            $this->redirect($this->_callbackUrl);
        }
    }
    
    protected function redirect($redirectUrl)
    {
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    protected function getClient()
    {
        $token = $this->getToken();
        
        $this->_client = $this->_auth->getClient(
                $token['oauth_token'], 
			    $token['oauth_token_secret']);
        
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
                if($this->_automaticRevalidateUser) {
                    $this->validateOAuthAccess();
                } else {
                    throw new Exception("Your user session has expired.");
                }
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
