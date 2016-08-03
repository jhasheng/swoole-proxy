<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/7/18
 * Time: 15:01
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Buffer;
use Swoole\Client;
use Swoole\Server;

class SwooleServer
{
    /**
     * @var \SS\Backend[]
     */
    public $clients = [];

    public $auth = false;

    public function start()
    {
        $server = new Server('0.0.0.0', 10005, SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $server->set([
            'max_conn'           => 500,
            'daemonize'          => false,
            'reactor_num'        => 1,
            'worker_num'         => 1,
            'dispatch_mode'      => 2,
            'buffer_output_size' => 2 * 1024 * 1024,
            'open_cpu_affinity'  => true,
            'open_tcp_nodelay'   => true,
            'log_file'           => 'socks5_server.log',
        ]);

        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

        $server->start();
    }

    public function onConnect(Server $server, $fd, $fromId)
    {
//        echo 'new client' . json_encode($server->getClientInfo($fd)) . PHP_EOL;
        $backend              = new Backend();
        $backend->isConnected = true;
        $backend->status      = Backend::STATUS_INIT;
        $backend->full        = new Buffer();
        $this->clients[$fd]   = $backend;
    }

    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $backend = $this->clients[$fd];
        $buffer  = new Buffer();
        $buffer->append($data);
        if (Backend::STATUS_INIT == $backend->status) {
            if (5 == bin2hex($buffer->substr(0, 1))) {
                if (!$this->auth) { // 不需要验证，发送0500 十六进制
                    $server->send($fd, pack('C2', 0x05, 0x00));
                    $backend->status = Backend::STATUS_BIND;
                } else {    // 需要验证，发送0502
                    $server->send($fd, pack('C2', 0x05, 0x02));
                    $backend->status = 'AUTH';
                }
            } else {
                $server->close($fd);
            }
            $buffer->clear();
        } else if (Backend::STATUS_BIND == $backend->status) {
            $domain = $port = null;
            $backend->atyp = bin2hex($buffer->substr(3, 1));
            if (Backend::ATYP_IPV4 == $backend->atyp) {  // ipv4
                $domain = long2ip(hexdec(bin2hex($buffer->substr(4, 4))));
                $port   = hexdec(bin2hex($buffer->substr(8, 2)));
            } else if (Backend::ATYP_DOMAIN == $backend->atyp) {   // domain
                $length = hexdec(bin2hex($buffer->substr(4, 1)));
                $domain = $buffer->substr(5, $length);
                $port = hexdec(bin2hex($buffer->substr($length + 5, 2)));
//                } else if (4 == $type) {   // ipv6
            } else {
                $server->close($fd);
            }

            $remote = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

            $remote->on('connect', function (Client $cli) use ($backend, $server, $fd, $remote) {
                $server->send($fd, pack('C10', 0x05, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00));
                $backend->status = Backend::STATUS_CONNECT;
                $backend->remote = $remote;
            });

            $remote->on('error', function (Client $cli) use ($server, $fd) {
                echo "Error: " . $cli->errCode . PHP_EOL;
                $server->close($fd);
            });

            $remote->on('close', function (Client $cli) use ($server, $fd, $backend) {
                $backend->remote = null;
            });

            $remote->on('receive', function (Client $cli, $data) use ($server, $fd, $backend) {
                if ($backend->isConnected) {
                    $server->send($fd, $data);
                }
            });

            if (3 == $backend->atyp) {
                swoole_async_dns_lookup($domain, function ($host, $ip) use ($remote, $port, $backend) {
                    echo $ip . ":" . $port . PHP_EOL;
                    $remote->connect($ip, $port);
                });
            } else {
                echo $domain . ":" . $port . PHP_EOL;
                $remote->connect($domain, $port);
                $buffer->clear();
            }
        } else if (Backend::STATUS_CONNECT == $backend->status) {
            if ($backend->remote === null) {
                echo 'remote connection has been closed.', PHP_EOL;
                return;
            }

//            var_dump($data);
//            echo '=======' . PHP_EOL;
            $sendByteCount = $backend->append($data)->request();
            if ($sendByteCount === false) {
                echo 'data length:', $backend->full->length, ' send byte count:', $sendByteCount, PHP_EOL;
                $server->close($fd);
            }
        }
    }

    public function onClose(Server $server, $fd, $fromId)
    {
        echo $fd . '==> closed' . PHP_EOL;
        $client              = $this->clients[$fd];
        $client->isConnected = false;
    }
}