<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## Basic class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Authentication
 * @since 1.0
 */

namespace Jira\Api\Authentication;

class Basic implements AuthenticationInterface
{
    private $_user_id;
    private $_password;

    public function __construct($user_id, $password)
    {
        $this->_user_id  = $user_id;
        $this->_password = $password;
    }

    public function getCredential()
    {
        return base64_encode($this->_user_id . ':' . $this->_password);
    }

    public function getId()
    {
        return $this->_user_id;
    }

    public function getPassword()
    {
        return $this->_password;
    }
}
