<?php
/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 7/23/2016
 * Time: 3:05 PM
 */

namespace SS;


use Swoole\Buffer;
use Swoole\Client;
use Swoole\Server;

class SwooleProxy
{

    protected $clients = [];

    public function start()
    {
        $server = new Server('0.0.0.0', 8008, SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $server->set([
            'max_conn'           => 500,
            'daemonize'          => false,
            'reactor_num'        => 1,
            'worker_num'         => 1,
            'dispatch_mode'      => 2,
            'buffer_output_size' => 2 * 1024 * 1024,
            'open_cpu_affinity'  => true,
            'open_tcp_nodelay'   => true,
            'log_file'           => 'socks5_http_server.log',
        ]);

        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

        $server->start();
    }

    public function onConnect(Server $server, $fd, $fromId)
    {
        $backend              = new Backend();
        $backend->isConnected = true;
        $backend->status      = Backend::STATUS_INIT;
        $backend->full        = new Buffer();
        $backend->startTime   = microtime();
        $this->clients[$fd]   = $backend;
    }

    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $headers = $this->parseHeaders($data);
        $request = explode(' ', $headers[0]);
        $backend = $this->clients[$fd];

        $that = $this;

        if ($request[0] != 'CONNECT') {
            $host = trim(explode(': ', $headers[1])[1]);
            echo $host . PHP_EOL;

            $client = new Client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

            $client->on('connect', function (Client $cli) use ($client, $backend) {
                $cli->send(pack('C2', 0x05, 0x00));
                $backend->status = Backend::STATUS_BIND;
                $backend->remote = $client;
            });

            $client->on('receive', function (Client $cli, $recieved) use ($backend, $host, $data, $server, $fd, $that) {
                $buffer = new Buffer();
                $buffer->append($recieved);
                if (Backend::STATUS_BIND == $backend->status) {
                    $send = new Buffer();
                    $send->append(pack('C4', 0x05, 0x02, 0x00, 0x03));
                    $send->append(pack('C1', strlen($host)));
//                    var_dump($that->isBigEndian());
                    $send->append($host);
                    $send->append(pack('C2', 0x00, 0x50));
//                    var_dump(bin2hex($send->substr(0, -1)));
                    if ($buffer->substr(1, 1) == 0x00) {
                        $cli->send($send->substr(0, -1));
                        $backend->status = Backend::STATUS_CONNECT;
                    }
                    $send->clear();
                } else if (Backend::STATUS_CONNECT == $backend->status) {
                    if ($buffer->substr(1, 1) == 0x00) {
                        $cli->send($data);
                        $backend->status = Backend::STATUS_COMPLETE;
                    }
                    $buffer->clear();
                } else if (Backend::STATUS_COMPLETE == $backend->status) {
//                    var_dump($recieved);
                    $server->send($fd, $recieved);
                }

            });
            $client->on('error', function (Client $cli) use ($server, $fd) {
                echo $cli->errCode . PHP_EOL;
                $server->close($fd);
            });

            $client->on('close', function (Client $cli) use ($server, $fd, $backend) {
                echo 'closed' . PHP_EOL;
                $backend->endTime = microtime();
//                $backend->remote = null;
            });

            $client->connect('0.0.0.0', 8009, 0.1);
        }
    }

    public function onClose(Server $server, $fd, $fromId)
    {

    }

    protected function parseHeaders($data)
    {
        return preg_split('/\n/', $data);
//        var_dump(strpos('\n', $data));
//        $headers = explode('\r\n', $data);
//        var_dump($headers);
    }

    protected function isBigEndian()
    {
        $bin = pack("L", 0x12345678);
        $hex = bin2hex($bin);
        if (ord(pack("H2", $hex)) === 0x78) {
            return false;
        }
        return true;
    }
}