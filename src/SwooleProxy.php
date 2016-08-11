<?php
/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 7/23/2016
 * Time: 3:05 PM
 */

namespace SS;


use League\CLImate\CLImate;
use Swoole\Buffer;
use Swoole\Client;
use Swoole\Server;

class SwooleProxy
{

    const STATS_ROOT = __DIR__ . '/../stats';
//    protected $socksAddress = '0.0.0.0:10005';
    protected $socksAddress;

    protected $socksAuth;

    protected $supportRouter = ['/stats', '/'];

    protected $host;

    protected $port;

    /**
     * @var CLImate
     */
    protected $cli;
    /**
     * @var SwooleClient[]
     */
    protected $agents = [];
    
    protected $config = [
        'max_conn'           => 500,
        'daemonize'          => false,
        'reactor_num'        => 1,
        'worker_num'         => 1,
        'dispatch_mode'      => 2,
        'buffer_output_size' => 2 * 1024 * 1024,
        'open_cpu_affinity'  => true,
        'open_tcp_nodelay'   => true,
//        'log_file'           => __CLASS__ .'.log',
    ];

    public function listen($port, $ip = '0.0.0.0')
    {
        $this->port = $port;
        $this->host = $ip;
        $this->cli = new CLImate();
        $server    = new Server($ip, $port, SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $server->set($this->config);
        $server->on('connect', [$this, 'onConnect']);
        $server->on('receive', [$this, 'onReceive']);
        $server->on('close', [$this, 'onClose']);

        $server->start();
    }

    public function setConfig($name, $value)
    {
        $this->config[$name] = $value;
    }

    public function setSocksServer($addr, $auth = null)
    {
        $this->socksAddress = $addr;
        $this->socksAuth = $auth;
    }

    public function onConnect(Server $server, $fd, $fromId)
    {
        $agent             = new SwooleClient();
        $this->agents[$fd] = $agent;
    }

    public function onReceive(Server $server, $fd, $fromId, $clientData)
    {
        $agent = $this->agents[$fd];
        if (!$agent->https) {
            $headers = $this->parseHeaders($clientData);
            if (strpos($headers[0], 'CONNECT') === 0) {
                $agent->https  = true;
                $addr          = explode(':', str_replace('Host:', '', $headers[4]));
                $agent->host   = trim($addr[0]);
                $agent->port   = trim($addr[1]);
                $agent->status = 1;
                $server->send($fd, "HTTP/1.1 200 Connection Established\r\n\r\n");
                $this->cli->green($headers[0]);
                return;
            } else {
                $addr = explode(':', str_replace('Host:', '', $headers[1]));
                $this->cli->green($headers[0]);
                $agent->host   = trim($addr[0]);
                $agent->port   = isset($addr[1]) ? isset($addr[1]) : 80;

                if ($this->isFaviconRequest($headers[0])) {
                    $data = file_get_contents(__DIR__ . '/../stats/img/favicon.ico');
                    $server->send($fd, "HTTP/1.1 200 OK\r\nContent-Type: image/png\r\nX-Powered-By: Swoole\r\n\r\n{$data}");
                    $server->close($fd);
                } else if (in_array(trim($addr[0]), ['127.0.0.1', 'localhost', '192.168.56.1'])) {
                    $path = $this->isStatRequest($headers[0]);
                    if ($path == '/') {
                        $fileName = '/index.html';
                        $data = file_get_contents(realpath(self::STATS_ROOT . $fileName));
                    } elseif ($path == '/view') {
                        $data = json_encode($server->stats());
                    } else {
                        $data = file_get_contents(realpath(self::STATS_ROOT . $path));
                    }

                    $server->send($fd, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nX-Powered-By: Swoole\r\n\r\n{$data}");
                    $server->close($fd);
                } else {
                    $agent->status = 1;
                }
            }
        }

        if ($agent->status == 1) {
            $remote = new Client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);

            $remote->on('connect', function (Client $cli) use ($remote, $agent, $clientData) {
                if ($this->socksAddress) {
                    $cli->send(pack('C2', 0x05, 0x00));
                } else {
                    $cli->send($clientData);
                }
                $agent->remote = $remote;
            });

            $remote->on('receive', function (Client $cli, $proxyData) use ($clientData, $agent, $server, $fd) {
                $buffer = new Buffer();
                $buffer->append($proxyData);
                if (!$this->socksAddress) {
                    $server->send($fd, $proxyData);
                    $agent->status = 3;
                } else {
                    if (0x00 == $buffer->substr(1, 1) && $agent->status == 1) {
                        $cli->send(pack('C5', 0x05, 0x02, 0x00, 0x03, strlen($agent->host)) . $agent->host . pack('n', $agent->port));
                        $agent->status = 2;
                    } else if ($agent->status == 2) {
                        $cli->send($clientData);
                        $agent->status = 3;
                    } else if ($agent->status == 3) {
                        $server->send($fd, $proxyData);
                    }
                }
                $buffer->clear();
            });

            $remote->on('error', function (Client $cli) use ($server, $fd) {
                echo swoole_strerror($cli->errCode) . PHP_EOL;
//                $cli->close();
            });

            $remote->on('close', function (Client $cli) use ($server, $fd, $agent) {
                $agent->remote = null;
            });

            if ($this->socksAddress) {
                list($ip, $port) = explode(':', $this->socksAddress);
                $remote->connect($ip, $port, 1);
            } else {
                swoole_async_dns_lookup($agent->host, function ($host, $ip) use ($agent, $remote) {
                    $remote->connect($ip, $agent->port);
                });
            }
        }

        if ($agent->status == 3 && $agent->remote != null && $agent->https) {
            $agent->remote->send($clientData);
        }
    }

    public function onClose(Server $server, $fd, $fromId)
    {
        $this->agents[$fd]->remote && $this->agents[$fd]->remote->close();
    }

    protected function parseHeaders($data)
    {
        return preg_split('/\n/', $data);
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

    protected function isStatRequest($request)
    {
        list($method, $url) = explode(' ', $request);
        $uri = parse_url($url);

        if (count($uri) == 1 && in_array($uri['path'], $this->supportRouter)) {
            return $uri['path'];
        } else {
            if (($method == 'GET' && in_array($uri['path'], $this->supportRouter) && $uri['port'] == $this->port)
                || !isset($uri['host'], $uri['port'])
            ) {
                return $uri['path'];
            }
            return false;
        }
    }

    /**
     * icon 请求， chrome默认会有此请求
     * @param $request
     * @return bool
     */
    protected function isFaviconRequest($request)
    {
        list($method, $uri) = explode(' ', $request);
        return $uri == '/favicon.ico';
    }
}