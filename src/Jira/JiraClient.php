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

class JiraClient
{
    const AUTH_BASIC = 'basic';
    const AUTH_OAUTH = 'oauth';
    
    protected $_authentication;
    protected $_token;
    protected $_client;

    public function __construct($config, $auth_type=null) {
        $this->_authentication = $auth_type ? $auth_type : self::AUTH_OAUTH;
        
        switch($auth_type) {
            case self::AUTH_OAUTH :
                $this->_client = new Api\Client\OauthClient($config);
            break;
        }
    }
    
    public function init()
    {
        if (!$this->_token) {
            $this->_token = $this->_client->getToken();
        }
        
        return $this->_token;
    }
    
    public function getIssue($issue_id)
    {
        if ($this->init()) {
            return $this->_client->getIssue($issue_id);
        } else {
            return false;
        }
    }
    
    public function createIssue(array $params)
    {
        if ($this->init()) {
            return $this->_client->createIssue($params);
        } else {
            return false;
        }
    }
}
