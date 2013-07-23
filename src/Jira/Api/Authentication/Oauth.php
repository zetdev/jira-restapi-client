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
    protected $baseUrl;
    protected $sandbox;
    protected $consumerKey;
    protected $consumerSecret;
    protected $callbackUrl;
    protected $requestTokenUrl = 'oauth';
    protected $accessTokenUrl = 'oauth';
    protected $authorizationUrl = 'OAuth.action?oauth_token=%s';

    protected $tokens;

    protected $client;
    protected $oauthPlugin;

    public function __construct($baseUrl) {
        $this->baseUrl = $baseUrl;
    }

    public function requestTempCredentials() {
        return $this->requestCredentials(
            $this->requestTokenUrl . '?oauth_callback=' . $this->callbackUrl    
        );
    }
    
    public function requestAuthCredentials($token, $tokenSecret, $verifier) {
        return $this->requestCredentials(
            $this->accessTokenUrl . '?oauth_callback=' . $this->callbackUrl . '&oauth_verifier=' . $verifier,
            $token,
            $tokenSecret
        );
    }

    protected function requestCredentials($url, $token = false, $tokenSecret = false) {
        $client = $this->getClient($token, $tokenSecret);

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

        $this->tokens = $tokens;
        return $this->tokens;
    }

    public function getClient($token = false, $tokenSecret = false) {
        if (!is_null($this->client)) {
            return $this->client;
        } else {
            $this->client = new Client($this->baseUrl);

            $this->oauthPlugin = new OauthPlugin(array(
                'consumer_key'      => $this->consumerKey,
                'consumer_secret'   => $this->consumerSecret,
                'token'             => !$token ? $this->tokens['oauth_token'] : $token,
                'token_secret'      => !$token ? $this->tokens['oauth_token_secret'] : $tokenSecret,
                'signature_method' => 'RSA-SHA1',
                'signature_callback' => function($stringToSign, $key) {
                    if (!file_exists($this->privateKey)) {
                        throw new \InvalidArgumentException("Private key {$this->privateKey} does not exist");
                    }

                    $certificate = openssl_pkey_get_private('file://' . $this->privateKey);

                    $privateKeyId = openssl_get_privatekey($certificate);

                    $signature = null;

                    openssl_sign($stringToSign, $signature, $privateKeyId);
                    openssl_free_key($privateKeyId);

                    return $signature;
                }
            ));

            $this->client->addSubscriber($this->oauthPlugin);

            return $this->client;
        }
    }

    public function makeAuthUrl() {
        return $this->baseUrl . sprintf($this->authorizationUrl, urlencode($this->tokens['oauth_token']));
    }

    public function setConsumerKey($consumerKey) {
        $this->consumerKey = $consumerKey;
        return $this;
    }

    public function setConsumerSecret($consumerSecret) {
        $this->consumerSecret = $consumerSecret;
        return $this;
    }

    public function setCallbackUrl($callbackUrl) {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    public function setRequestTokenUrl($requestTokenUrl) {
        $this->requestTokenUrl = $requestTokenUrl;
        return $this;
    }

    public function setAccessTokenUrl($accessTokenUrl) {
        $this->accessTokenUrl = $accessTokenUrl;
        return $this;
    }

    public function setAuthorizationUrl($authorizationUrl) {
        $this->authorizationUrl = $authorizationUrl;
        return $this;
    }
    
    public function setPrivateKey($privateKey) {
        $this->privateKey = $privateKey;
        return $this;
    }



    
 //    protected $_options = array(
 //        'base_url'            => '',
 //        'authorization_url'   => '{base_url}/plugins/servlet/oauth/authorize?oauth_token=%s',
 //        'request_token_url'   => '{base_url}/plugins/servlet/oauth/request-token',
 //        'access_token_url'    => '{base_url}/plugins/servlet/oauth/access-token',
 //        'api_url'             => '{base_url}/rest/api/2',
 //        'infos_url'           => '{base_url}/rest/api/2/user',

 //        'signature_method'    => 'RSA-SHA1',
 //    );

 //    public function __construct($options = null)
 //    {
 //        if (is_array($options)) {
 //            foreach ($options AS $key => $value) {
 //                $this->setOption($key, $value);
 //            }
 //        }
 //    }
    
 //    public function getOption($name)
 //    {
 //        if (strpos($name, '_url') !== false) {
 //            return str_replace('{base_url}', $this->_options['base_url'], $this->_options[$name]);
 //        }
 //        return !empty($this->_options[$name]) ? $this->_options[$name] : '';
 //    }

 //    public function setOption($name, $value)
 //    {
 //        return $this->_options[$name] = $value;
 //    }
    
 //    public function getClient($token = false, $tokenSecret = false) {
	// 	if (!is_null($this->_client)) {
	// 		return $this->_client;
	// 	} else {
	// 		$this->_client = new Client($this->getOption('base_url'));

	// 		$this->_oauthPlugin = new OauthPlugin(array(
	// 			'consumer_key' 		 => $this->getOption('consumer_key'),
	// 			'consumer_secret' 	 => $this->getOption('consumer_secret'),
	// 			'token' 			 => !$token ? $this->_tokens['oauth_token'] : $token,
	// 			'token_secret' 		 => !$token ? $this->_tokens['oauth_token_secret'] : $tokenSecret,
	//             'signature_method'   => $this->getOption('signature_method'),
	//             'signature_callback' => function($stringToSign, $key) {
	// 				if (!file_exists($this->getOption('private_key'))) {
	// 					throw new InvalidArgumentException("Private key {$this->getOption('private_key')} does not exist");
	// 				}

	// 				$certificate = openssl_pkey_get_private('file://' . $this->getOption('private_key'));

	// 				$privateKeyId = openssl_get_privatekey($certificate);

	// 				$signature = null;

	// 				openssl_sign($stringToSign, $signature, $privateKeyId);
	// 				openssl_free_key($privateKeyId);

	// 				return $signature;
	//             }
	// 		));

	// 		$this->_client->addSubscriber($this->_oauthPlugin);

 //            echo "<pre>";
 //            print_r($this->_client);
 //            echo "</pre>";
 //            exit;

	// 		return $this->_client;
	// 	}
	// }
    
 //    public function requestTempCredentials() {
	// 	return $this->requestCredentials(
	// 		$this->getOption('request_token_url') . '?oauth_callback=' . $this->getOption('callback_url')
	// 	);
	// }
	
	// public function requestAuthCredentials($token, $tokenSecret, $verifier) {
	// 	return $this->requestCredentials(
	// 		$this->getOption('access_token_url') . '?oauth_callback=' . $this->getOption('callback_url') . '&oauth_verifier=' . $verifier,
	// 		$token,
	// 		$tokenSecret
	// 	);
	// }

 //    public function requestCredentials($url, $token = false, $tokenSecret = false) {
 //        $client = $this->getClient($token, $tokenSecret);

 //        $response = $client->post($url)->send();

 //        return $this->makeTokens($response);
 //    }

 //    protected function makeTokens($response) {
 //        $body = (string)$response->getBody();

 //        $tokens = array();
 //        parse_str($body, $tokens);

 //        if (empty($tokens)) {
 //            throw new Exception("An error occurred while requesting oauth token credentials");
 //        }

 //        $this->_tokens = $tokens;
 //        return $this->_tokens;
 //    }

 //    public function makeAuthUrl() {
	// 	return sprintf($this->getOption('authorization_url'), urlencode($this->_tokens['oauth_token']));
	// }

}
