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
    const METHOD_GET = 'get';
    const METHOD_POST = 'post';
    const DATA_TYPE_JSON = 'json';
    
    protected $auth;
    protected $client;
    
    protected $token;
    protected $automatic_revalidate_user = true;

    protected $request_token_uri = 'plugins/servlet/oauth/request-token';
    protected $authorization_uri = 'plugins/servlet/oauth/authorize?oauth_token=%s';
    protected $access_token_uri = 'plugins/servlet/oauth/access-token';
    protected $rest_api_uri = 'rest/api/2';

    protected $api_url;
    protected $callback_url;
    
    public function __construct($config) {
        
        $this->auth = new Oauth($config['base_url']);
        $this->auth->setPrivateKey($config['private_key'])
          ->setConsumerKey($config['consumer_key'])
          ->setConsumerSecret($config['consumer_secret'])
          ->setRequestTokenUrl($this->request_token_uri)
          ->setAuthorizationUrl($this->authorization_uri)
          ->setAccessTokenUrl($this->access_token_uri)
          ->setCallbackUrl($config['callback_url']);

        $this->api_url = $config['base_url'].$this->rest_api_uri;
        $this->callback_url = $config['callback_url'];
    }

    public function getToken()
    {
        if (session_status() != 2) { session_start(); }

        if (empty($this->token)) {
            if (!isset($_SESSION['JIRA_ACCESS_TOKEN']) && isset($_SESSION['JIRA_REQUEST_TOKEN'])) {
                $this->getAccessToken();
            }
            elseif (!isset($_SESSION['JIRA_ACCESS_TOKEN'])) {
                $this->validateOAuthAccess();
            }

            $this->token = unserialize($_SESSION['JIRA_ACCESS_TOKEN']);
        }
        return $this->token;
    }
    
    public function getIssue($issue_id) {
        $url = $this->api_url.'/issue/'.$issue_id;
        
        return $this->sendRequest($url, null, self::METHOD_GET);
    }

    public function createIssue(array $params) {
        $url = $this->api_url.'/issue/';
        
        return $this->sendRequest($url, $params, self::METHOD_POST);
    }
    
    protected function validateOAuthAccess()
    {
        $token = $this->auth->requestTempCredentials();
        
        $_SESSION['JIRA_REQUEST_TOKEN'] = serialize($token);

        $redirect = $this->auth->makeAuthUrl();
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

            $this->token = $this->auth->requestAuthCredentials(
                $tempToken['oauth_token'],
                $tempToken['oauth_token_secret'],
                $verifier
            );
            $_SESSION['JIRA_ACCESS_TOKEN'] = serialize($this->token);
        }
        else {
            die('Bad Request Token');
        }
        
        if ($redirect) {
            $this->redirect($this->callback_url);
        }
    }
    
    protected function redirect($redirectUrl)
    {
        header('Location: ' . $redirectUrl);
        exit(1);
    }
    
    protected function getClient()
    {
        $token = $this->getToken();
        
        $this->client = $this->auth->getClient(
                $token['oauth_token'], 
			    $token['oauth_token_secret']);
        
        return $this->client;
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
        if (!($this->client instanceof Oauth)) {
            $this->client = $this->getClient();
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
                    $request = $this->client->post($url,
                            array('Content-type' => 'application/json'), $data);
                } else {
                    $request = $this->client->get($url,
                            array('Content-type' => 'application/json'));
                }
            break;
        }
        
        if (isset($request)) {
            $response = $request->send();
            
            if ($response->getStatusCode() == 401)
            {
                if($this->automatic_revalidate_user) {
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
