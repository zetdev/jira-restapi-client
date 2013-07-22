<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## Anonymous class
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Authentication
 * @since 1.0
 */

namespace Jira\Api\Authentication;

class Anonymous implements AuthenticationInterface
{
    public function __construct()
    {
    }
    
    public function getCredential()
    {
        return NULL;
    }
}
