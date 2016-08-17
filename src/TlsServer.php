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
        'log_file'           => __DIR__ . '/../https_server.log',
        'ssl_cert_file'      => __DIR__ . '/../cert/ca.cer',
        'ssl_key_file'       => __DIR__ . '/../cert/ca.key',
//        'ssl_method'         => SWOOLE_TLSv1_METHOD,
//        'ssl_ciphers'        => ''
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
            echo "new client({$fd}) joined" . PHP_EOL;
        });

        $https->on('receive', function (Server $server, $fd, $fromId, $data) {
            $buffer = new SuperBuffer();
            $buffer->append($data);
            echo 'Content-Type: ' . $type = $buffer->substr2Dec(0, 1) . PHP_EOL;
            echo 'Major Version: ' . $buffer->substr2Hex(0, 1) . PHP_EOL;
            echo 'Second Version: ' . $buffer->substr2Hex(0, 1) . PHP_EOL;
            $length = $buffer->substr2Dec(0, 2);
            echo 'Content-Length: ' . $length . PHP_EOL;
            echo 'Encrypted Data: ' . $data = $buffer->substr(0, $length) . PHP_EOL;
            echo 'Last Data: ' . $buffer->length . PHP_EOL;
            if ($type == 22) { // handshake
                $dataBuffer = new SuperBuffer();
                $dataBuffer->append($data);
                echo '==> Handshake Type: ' . $dataBuffer->substr2Dec(0, 1) . PHP_EOL;
                echo '==> Length: ' . $dataBuffer->substr2Dec(0, 3) . PHP_EOL;
                echo '==> Major Version: ' . $dataBuffer->substr2Dec(0, 1) . PHP_EOL;
                echo '==> Second Version: ' . $dataBuffer->substr2Dec(0, 1) . PHP_EOL;
                echo '==> Random GMT Unix Time: ' . $dataBuffer->substr2Dec(0, 4) . PHP_EOL;
                echo '==> Random Bytes: ' . $dataBuffer->substr2Hex(0, 28) . PHP_EOL;
                $sidLength = $dataBuffer->substr2Dec(0, 1);
                echo '==> Session ID length: ' . $sidLength . PHP_EOL;
                if ($sidLength > 0) echo '===> Session ID: ' . $dataBuffer->substr2Dec(0, $sidLength) . PHP_EOL;
                $cipherLength = $dataBuffer->substr2Dec(0, 2);
                echo '==> Cipher Suites Length: ' . $cipherLength . PHP_EOL;
                echo '==> Cipher Suites: ' . $dataBuffer->substr2Hex(0, $cipherLength) . PHP_EOL;
                $methodLength = $dataBuffer->substr2Dec(0, 1);
                echo '==> Compression Method Length: ' . $methodLength . PHP_EOL;
                echo '==> Compression Method: ' . $dataBuffer->substr2Dec(0, $methodLength) . PHP_EOL;
                $dataBuffer->clear();
            } else if ($type == 23) { // application data

            } else if ($type == 21) { // change_chipher_spec

            } else {
                echo 'invalid type!!' . PHP_EOL;
            }
            $buffer->clear();
        });

        $https->on('close', function (Server $server, $fd, $fromId) {
            echo "client({$fd}) left" . PHP_EOL;
        });

        $https->start();
    }
}