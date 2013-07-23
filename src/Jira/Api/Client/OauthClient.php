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
    protected $automatic_revalidate_user = false;
    
    public function __construct($config) {
        
        $this->auth = new Oauth($config['base_url']);
        $this->auth->setPrivateKey($config['private_key'])
          ->setConsumerKey($config['consumer_key'])
          ->setConsumerSecret($config['consumer_secret'])
          ->setRequestTokenUrl('plugins/servlet/oauth/request-token')
          ->setAuthorizationUrl('plugins/servlet/oauth/authorize?oauth_token=%s')
          ->setAccessTokenUrl('plugins/servlet/oauth/access-token')
          ->setCallbackUrl($config['callback_url']);
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

            $this->token = $_SESSION['JIRA_ACCESS_TOKEN'];
        }
        return $this->token;
    }
    
    public function getIssue($issue_id) {
        $url = $this->auth->getOption('api_url').'/issue/'.(int)$issue_id;
        
        return $this->sendRequest($url, null, self::METHOD_GET);
    }

    public function createIssue(array $params) {
        $url = $this->auth->getOption('api_url').'/createissue';
        
        return $this->sendRequest($url, $params, self::METHOD_POST);
    }
    
    protected function validateOAuthAccess()
    {
        $token = $this->auth->requestTempCredentials();
        
        $_SESSION['JIRA_REQUEST_TOKEN'] = $token;

        $redirect = $this->auth->makeAuthUrl();
        $this->redirect($redirect);
    }
    
    protected function getAccessToken($redirect=false)
    {
        $verifier = isset($_REQUEST['oauth_verifier']) ? $_REQUEST['oauth_verifier'] : '';

        if (empty($verifier)) {
            throw new InvalidArgumentException("There was no oauth verifier in the request");
        }

        if($_SESSION['JIRA_REQUEST_TOKEN'])
        {
            $tempToken = $_SESSION['JIRA_REQUEST_TOKEN'];

            $this->token = $this->auth->requestAuthCredentials(
                $tempToken['oauth_token'],
                $tempToken['oauth_token_secret'],
                $verifier
            );

            $_SESSION['JIRA_ACCESS_TOKEN'] = $this->token;
        }
        else {
            die('Bad Request Token');
        }
        
        if ($redirect) {
            $this->redirect($redirect);
        }
        
        return isset($_SESSION['JIRA_ACCESS_TOKEN']);
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
     * @param string $uri
     * @param mixed $data
     * @param string $data_type
     * @param string $method
     * @return array|string
     * @throws Exception
     */
    protected function sendRequest($url, $data=null, $method=null, $data_type=null)
    {
        if (!($this->client instanceof Oauth)) {
            throw new Exception("OauthClient not exists.");
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
                    $request = $this->client->post($uri,
                            array('Content-type' => 'application/json'), $data);
                } else {
                    $request = $this->client->get($uri);
                }
        }
        
        if (isset($request)) {
            $response = $request->send();
            
            if($response->getStatus() == 401)
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
