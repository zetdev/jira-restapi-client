<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## JiraClient class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira
 * @since 1.0
 */

namespace Jira;

use Jira\Api\Client\OauthClient;

class JiraClient
{
    const AUTH_BASIC = 'basic';
    const AUTH_OAUTH = 'oauth';
    
    protected $authentication;
    protected $token;
    protected $client;

    public function __construct($config, $auth_type=null) {
        $this->authentication = $auth_type ? $auth_type : self::AUTH_OAUTH;


        switch($this->authentication) {
            case self::AUTH_OAUTH :
                $this->client = new OauthClient($config);
            break;
        }
    }
    
    public function init()
    {
        if (!$this->token) {
            $this->token = $this->client->getToken();
        }
        
        return $this->token;
    }
    
    public function getIssue($issue_id)
    {
        if ($this->init()) {
            return $this->client->getIssue($issue_id);
        } else {
            return false;
        }
    }
    
    public function createIssue(array $params)
    {
        if ($this->init()) {
            return $this->client->createIssue($params);
        } else {
            return false;
        }
    }
}
