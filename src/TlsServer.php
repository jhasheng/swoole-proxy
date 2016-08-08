<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/8
 * Time: 13:16
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Server;

//openssl req -x509 -newkey rsa:1024 -keyout ca.key -out ca.cer -day 3650 -config openssl.cnf

class TlsServer
{
    protected $config = [
        'max_conn'           => 500,
        'daemonize'          => false,
//        'reactor_num'        => 1,
//        'worker_num'         => 1,
        'dispatch_mode'      => 2,
        'buffer_output_size' => 2 * 1024 * 1024,
//        'open_cpu_affinity'  => true,
//        'open_tcp_nodelay'   => true,
        'log_file'           => 'https_server.log',
        'ssl_cert_file'      => __DIR__ . '/../cert/ca.cer',
        'ssl_key_file'       => __DIR__ . '/../cert/ca_nopwd.key'
    ];

    public function setConfig($name, $value)
    {
        $this->config[$name] = $value;
    }


    public function listen($ip, $port)
    {
        $https = new Server($ip, $port, SWOOLE_BASE, SWOOLE_SOCK_TCP | SWOOLE_SSL);

        $https->set($this->config);

        $https->on('connect', function (Server $server, $fd, $fromId) {
            echo "new client({$fd}) joined";
        });

        $https->on('receive', function (Server $server, $fd, $fromId, $data) {
            var_dump($data);
        });

        $https->on('close', function (Server $server, $fd, $fromId) {
            echo "client({$fd}) left";
        });

        $https->start();
    }
}