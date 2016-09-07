<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/2
 * Time: 14:28
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Client;
use Zend\Diactoros\Request;

class Agent
{
    /**
     * @var Client
     */
    public $remote = null;

    /**
     * @var Request
     */
    public $request = null;
    
    public $https = false;
}