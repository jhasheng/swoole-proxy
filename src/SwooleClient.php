<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/1
 * Time: 15:26
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Client;

class SwooleClient
{
    public $status = 0;
    
    public $https = false;
    
    public $host;
    
    public $port;

    /**
     * @var Client
     */
    public $remote;
}