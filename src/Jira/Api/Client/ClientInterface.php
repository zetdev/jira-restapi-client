<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 *## ClientInterface interface
 *
 * @author Alex Zet <zetdev@gmail.com>
 * @copyright 2013 Alex Zet <https://github.com/alexzv>
 * @license http://www.opensource.org/licenses/MIT MIT License
 * 
 * @package Jira\Api\Client
 * @since 1.0
 */

namespace Jira\Api\Client;

interface ClientInterface
{
    public function getToken();
    
    public function getIssue($issue_id);
    
    public function createIssue(array $params);
    
}
