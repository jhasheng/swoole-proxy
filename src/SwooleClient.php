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

    public $isChuncked = false;
    
    public $endChuncked = false;

    /**
     * @var Client
     */
    public $mitmRemote = null;

    public $isConnectMitm = false;

    public $code = 200;

    public $data = ['header' => '', 'response' => '', 'ip' => '', 'length' => ''];

    public function connectMitm()
    {
        if ($this->mitmRemote && !$this->isConnectMitm) {
            $this->mitmRemote->connect('0.0.0.0', 10005);
        }
    }
}