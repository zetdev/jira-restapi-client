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
    protected $_client;
    protected $_oauthPlugin;
    protected $_tokens;
    
    protected $_options = array(
        'base_url'            => '',
        'authorization_url'   => '{base_url}/plugins/servlet/oauth/authorize?oauth_token=%s',
        'request_token_url'   => '{base_url}/plugins/servlet/oauth/request-token',
        'access_token_url'    => '{base_url}/plugins/servlet/oauth/access-token',
        'api_url'             => '{base_url}/rest/api/2',
        'infos_url'           => '{base_url}/rest/api/2/user',

        'signature_method'    => 'RSA-SHA1',
    );

    public function __construct($options = null)
    {
        if (is_array($options)) {
            foreach ($options AS $key => $value) {
                $this->setOption($key, $value);
            }
        }
    }
    
    public function getOption($name)
    {
        if (in_array($name, array('authorization_url', 'request_token_url', 'access_token_url', 'infos_url'))) {
            return str_replace('{base_url}', $this->_options['base_url'], $this->_options[$name]);
        }
        return !empty($this->_options[$name]) ? $this->_options[$name] : '';
    }

    public function setOption($name, $value)
    {
        return $this->_options[$name] = $value;
    }
    
    public function getClient($token = false, $tokenSecret = false) {
		if (!is_null($this->_client)) {
			return $this->_client;
		} else {
			$this->_client = new Client($this->getOption('base_url'));

			$this->_oauthPlugin = new OauthPlugin(array(
				'consumer_key' 		 => $this->getOption('consumer_key'),
				'consumer_secret' 	 => $this->getOption('consumer_secret'),
				'token' 			 => !$token ? $this->_tokens['oauth_token'] : $token,
				'token_secret' 		 => !$token ? $this->_tokens['oauth_token_secret'] : $tokenSecret,
	            'signature_method'   => $this->getOption('signature_method'),
	            'signature_callback' => function($stringToSign, $key) {
					if (!file_exists($this->getOption('private_key'))) {
						throw new InvalidArgumentException("Private key {$this->getOption('private_key')} does not exist");
					}

					$certificate = openssl_pkey_get_private('file://' . $this->getOption('private_key'));

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
    
    public function requestTempCredentials() {
		return $this->requestCredentials(
			$this->getOption('request_token_url') . '?oauth_callback=' . $this->getOption('callback_url')
		);
	}
	
	public function requestAuthCredentials($token, $tokenSecret, $verifier) {
		return $this->requestCredentials(
			$this->getOption('access_token_url') . '?oauth_callback=' . $this->getOption('callback_url') . '&oauth_verifier=' . $verifier,
			$token,
			$tokenSecret
		);
	}

    public function requestCredentials($url, $token = false, $tokenSecret = false) {
        $client = $this->getClient($token, $tokenSecret);

        $response = $client->post($url)->send();

        return $this->makeTokens($response);
    }

    protected function makeTokens($response) {
        $body = (string)$response->getBody();

        $tokens = array();
        parse_str($body, $tokens);

        if (empty($tokens)) {
            throw new Exception("An error occurred while requesting oauth token credentials");
        }

        $this->_tokens = $tokens;
        return $this->_tokens;
    }

    public function makeAuthUrl() {
		return $this->getOption('base_url') . sprintf($this->getOption('authorization_url'), urlencode($this->_tokens['oauth_token']));
	}

}
