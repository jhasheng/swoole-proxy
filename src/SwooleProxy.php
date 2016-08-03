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

    /**
     * @var SwooleClient[]
     */
    protected $clients = [];

    public function start()
    {
        $server = new Server('0.0.0.0', 10004, SWOOLE_BASE, SWOOLE_SOCK_TCP);

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
        $client              = new SwooleClient();
        $this->clients[$fd]   = $client;
    }

    public function onReceive(Server $server, $fd, $fromId, $data)
    {
        $client = $this->clients[$fd];
        if (!$client->https) {
            $headers = $this->parseHeaders($data);
            if (strpos($headers[0], 'CONNECT') === 0) {
                $client->https = true;
                $addr = explode(':', str_replace('Host:', '', $headers[4]));
                $client->host = trim($addr[0]);
                $client->port = trim($addr[1]);
                $client->status = 1;
                $server->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
                echo trim($headers[0]) . PHP_EOL;
                return ;
            } else {
                $addr = explode(':', str_replace('Host:', '', $headers[1]));
                echo trim($headers[0]) . PHP_EOL;
                $client->host = trim($addr[0]);
                $client->port = isset($addr[1]) ? isset($addr[1]) : 80;
                $client->status = 1;
            }
        }

        if ($client->status == 1) {
            $remote = new Client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

            $remote->on('connect', function(Client $cli) use ($remote, $client){
                $cli->send(pack('C2', 0x05, 0x00));
                $client->remote = $remote;
            });

            $remote->on('receive', function(Client $cli, $received) use($data, $client, $server, $fd) {
                $buffer = new Buffer();
                $buffer->append($received);

                if (0x00 == $buffer->substr(1, 1) && $client->status == 1) {
                    $client->status = 2;
                    $buffer->clear();
                    $cli->send(pack('C5', 0x05, 0x02, 0x00, 0x03, strlen($client->host)) . $client->host . pack('n', $client->port));
                } else if ($client->status == 2) {
                    $cli->send($data);
                    $client->status = 3;
                } else if ($client->status == 3) {
                    $server->send($fd, $received);
                }
            });

            $remote->on('error', function (Client $cli) use ($server, $fd) {
                echo $cli->errCode . PHP_EOL;
            });

            $remote->on('close', function (Client $cli) use ($server, $fd) {
                echo 'closed' . PHP_EOL;
//                $backend->remote = null;
            });

            $remote->connect('0.0.0.0', 10005);
        }


        if ($client->status == 3 && $client->remote != null && $client->https) {
            $client->remote->send($data);
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