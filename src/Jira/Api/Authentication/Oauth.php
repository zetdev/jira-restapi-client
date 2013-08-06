<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## Oauth class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Authentication
 * @since 1.0
 */

namespace Jira\Api\Authentication;

use Jira\Exception\Exception;
use Jira\Exception\InvalidArgumentException;

use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;

class Oauth implements AuthenticationInterface
{
    protected $_baseUrl;
    protected $_consumerKey;
    protected $_consumerSecret;
    protected $_callbackUrl;
    protected $_requestTokenUrl = 'oauth';
    protected $_accessTokenUrl = 'oauth';
    protected $_authorizationUrl = 'OAuth.action?oauth_token=%s';

    protected $_client;
    protected $_tokens;
    protected $_oauthPlugin;

    protected $sandbox;

    public function __construct($baseUrl) {
        $this->_baseUrl = $baseUrl;
    }

    public function requestTempCredentials() {
        return $this->requestCredentials(
            $this->_requestTokenUrl . '?oauth_callback=' . $this->_callbackUrl    
        );
    }
    
    public function requestAuthCredentials($token, $token_secret, $verifier) {
        return $this->requestCredentials(
            $this->_accessTokenUrl . '?oauth_callback=' . $this->_callbackUrl . '&oauth_verifier=' . $verifier,
            $token,
            $token_secret
        );
    }

    protected function requestCredentials($url, $token = false, $token_secret = false) {
        $client = $this->getClient($token, $token_secret);

        $response = $client->post($url)->send();

        return $this->makeTokens($response);
    }

    protected function makeTokens($response) {
        $body = (string) $response->getBody();

        $tokens = array();
        parse_str($body, $tokens);

        if (empty($tokens)) {
            throw new Exception("An error occurred while requesting oauth token credentials");
        }

        $this->_tokens = $tokens;
        return $this->_tokens;
    }

    public function getClient($token = false, $token_secret = false) {
        if (!is_null($this->_client)) {
            return $this->_client;
        } else {
            $this->_client = new Client($this->_baseUrl);

            $this->_oauthPlugin = new OauthPlugin(array(
                'consumer_key'      => $this->_consumerKey,
                'consumer_secret'   => $this->_consumerSecret,
                'token'             => !$token ? $this->_tokens['oauth_token'] : $token,
                'token_secret'      => !$token ? $this->_tokens['oauth_token_secret'] : $token_secret,
                'signature_method' => 'RSA-SHA1',
                'signature_callback' => function($stringToSign, $key) {
                    if (!file_exists($this->_privateKey)) {
                        throw new \InvalidArgumentException("Private key {$this->_privateKey} does not exist");
                    }

                    $certificate = openssl_pkey_get_private('file://' . $this->_privateKey);

                    $privateKeyId = openssl_get_privatekey($certificate);

                    $signature = null;

                    openssl_sign($stringToSign, $signature, $privateKeyId);
                    openssl_free_key($privateKeyId);

                    return $signature;
                }
            ));

            $this->_client->addSubscriber($this->_oauthPlugin);

            return $this->_client;
        }
    }

    public function makeAuthUrl() {
        return $this->_baseUrl . sprintf($this->_authorizationUrl, urlencode($this->_tokens['oauth_token']));
    }

    public function setConsumerKey($consumer_key) {
        $this->_consumerKey = $consumer_key;
        return $this;
    }

    public function setConsumerSecret($consumer_secret) {
        $this->_consumerSecret = $consumer_secret;
        return $this;
    }

    public function setCallbackUrl($callback_url) {
        $this->_callbackUrl = $callback_url;
        return $this;
    }

    public function setRequestTokenUrl($request_token_url) {
        $this->_requestTokenUrl = $request_token_url;
        return $this;
    }

    public function setAccessTokenUrl($access_token_url) {
        $this->_accessTokenUrl = $access_token_url;
        return $this;
    }

    public function setAuthorizationUrl($authorization_url) {
        $this->_authorizationUrl = $authorization_url;
        return $this;
    }
    
    public function setPrivateKey($private_key) {
        $this->_privateKey = $private_key;
        return $this;
    }

}
