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
    const HTTPS_CLIENT_HELLO = 1;

    const HTTPS_SERVER_HELLO = 2;

    const HTTPS_CERTIFICATE = 3;

    const HTTPS_KEY_EXCHANGE = 4;

    public $status = 0;
    
    public $https = false;
    
    public $subStatus = 0;
    
    public $host;
    
    public $port;

    /**
     * @var Client
     */
    public $remote;

    public $tlsConnect;

    public $length = 0;
    
    public $contentLength = 0;

    public $data = ['header' => '', 'response' => '', 'ip' => ''];
}