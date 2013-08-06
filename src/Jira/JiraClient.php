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
    
    protected $_authentication;
    protected $_token;
    protected $_client;

    public function __construct($config, $auth_type=null) {
        $this->_authentication = $auth_type ? $auth_type : self::AUTH_BASIC;


        switch($this->_authentication) {
            case self::AUTH_OAUTH :
                $this->_client = new OauthClient($config);
            break;
        
            case self::AUTH_BASIC :
                $this->_client = new BaseClient($config);
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
