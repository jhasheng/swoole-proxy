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

    const STATS_INDEX_FILE = 'index.html';
//    protected $socksAddress = '0.0.0.0:10005';
    protected $socksAddress;

    protected $socksAuth;

    protected $supportRouter = ['/view'];

    protected $localAddress = [
        '192.168.56.1',
        '127.0.0.1',
        'localhost'
    ];

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

    /**
     * @var \Swoole\Http\Client
     */
    protected $ws = null;

    protected $config = [
        'max_conn'           => 500,
        'daemonize'          => false,
        'reactor_num'        => 1,
        'worker_num'         => 1,
        'dispatch_mode'      => 2,
        'buffer_output_size' => 3 * 1024 * 1024,
        //        'open_cpu_affinity'  => true,
        //        'open_tcp_nodelay'   => true,
        'open_eof_check'     => true,
        'package_eof'        => '\r\n',
        'log_file'           => 'http_proxy_server.log',
    ];

    public function listen($port, $ip = '0.0.0.0')
    {
        $this->port = $port;
        $this->host = $ip;
        $this->cli  = new CLImate();
        $server     = new Server($ip, $port, SWOOLE_BASE, SWOOLE_SOCK_TCP);

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
        $this->socksAuth    = $auth;
    }

    public function onConnect(Server $server, $fd, $fromId)
    {
        $agent             = new SwooleClient();
        $this->agents[$fd] = $agent;
        if (!$this->ws) {
            $ws = new \Swoole\Http\Client('0.0.0.0', 10005);
            $ws->on('connect', function (\Swoole\Http\Client $client) use ($ws) {
                $this->ws = $ws;
            });
            $ws->on('close', function (\Swoole\Http\Client $client) {
                $this->ws = null;
            });
            $ws->on('message', function (\Swoole\Http\Client $client, $frame) {
            });

            $ws->upgrade('/', function (\Swoole\Http\Client $client) use ($ws) {
                echo 'upgrade success' . PHP_EOL;
                $client->push('stats');
                $this->ws = $ws;
            });

            $ws->on('error', function (\Swoole\Http\Client $client) {
                echo 'error';
            });
        }
    }

    public function onReceive(Server $server, $fd, $fromId, $clientData)
    {
        $agent = $this->agents[$fd];

        if (!$agent->https) {
            $headers = $this->parseHeaders($clientData);
            $this->isLocalRequest($headers);
            if (strpos($headers[0], 'CONNECT') === 0) {
                $agent->data['header']   = base64_encode($clientData);
                $agent->data['response'] = base64_encode("HTTP/1.1 200 Connection Established\r\n\r\n");
                if ($this->ws) $this->ws->push(json_encode($agent->data));
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
                $this->cli->green($this->count . $headers[0]);
                $agent->host = trim($addr[0]);
                $agent->port = isset($addr[1]) ? isset($addr[1]) : 80;

                if ($this->isLocalRequest($headers)) {
                    list($method, $url, $protocol) = explode(' ', trim($headers[0]));
                    $uri = parse_url($url);
                    if ($uri['path'] == '/') {
                        $uri['path'] .= self::STATS_INDEX_FILE;
                        $data   = file_get_contents(self::STATS_ROOT . $uri['path']);
                        $mime   = 'text/html';
                        $status = '200 OK';
                    } else if (in_array($uri['path'], $this->supportRouter)) {
                        $data   = json_encode($server->stats());
                        $mime   = 'application/json';
                        $status = '200 OK';
                    } else {
                        if (file_exists(realpath(self::STATS_ROOT . $uri['path']))) {
                            $data   = file_get_contents(realpath(self::STATS_ROOT . $uri['path']));
                            $mime   = 'text/html';
                            $status = '200 OK';
                        } else {
                            $version = swoole_version();
                            $data    = "<center><h1>404 Not Found</h1></center><hr/><center>Swoole Server {$version}</center>";
                            $mime    = 'text/html';
                            $status  = '200 OK';
                        }
                    }
                    $server->send($fd, "HTTP/1.1 {$status}\r\nContent-Type: {$mime}\r\nX-Powered-By: Swoole\r\n\r\n{$data}");
                    $server->close($fd);
                } else {
                    $agent->data['header'] = base64_encode($clientData);
                    $agent->status         = 1;
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

            $remote->on('receive', function (Client $cli, $response) use ($clientData, $agent, $server, $fd) {
                $buffer = new Buffer();
                $agent->data['response'] .= $response;
                if (!$this->socksAddress) {
                    $agent->length += strlen($response);
                    if (!$agent->https) {
                        if (!$agent->isChuncked) {
                            foreach (preg_split('/\n/', $response) as $header) {
                                if (strpos($header, 'Transfer-Encoding: ') === 0) {
                                    $agent->isChuncked = true;
                                    break;
                                }
                                if (strpos($header, '304 Not') !== false) {
                                    $agent->code = 304;
                                }
                                if (strpos($header, 'Content-Length: ') === 0) {
                                    $agent->contentLength = (int) array_pop(explode(': ', $header));
                                    break;
                                }
                            }
                        } else {
                            $buffer->append($response);
                            $length = hexdec($buffer->substr(0, 4));
                            if (!$agent->contentLength && $length > 0) $agent->contentLength = $length;
                        }
//                        echo $agent->contentLength . ' => ' . $agent->length . PHP_EOL;
                    }

                    if (304 == $agent->code || ($agent->contentLength > 0 && $agent->length >= $agent->contentLength)) {
                        $agent->data['response'] = base64_encode($agent->data['response']);
                        $agent->data['length'] = $agent->length;
                        if ($this->ws && !$agent->https) {
                            $this->ws->push(json_encode($agent->data));
                            $agent->data = null;
                        }
                    }
                    $server->send($fd, $response);
                    $agent->status = 3;
                } else {
                    if (0x00 == $buffer->substr(1, 1) && $agent->status == 1) {
                        $cli->send(pack('C5', 0x05, 0x02, 0x00, 0x03, strlen($agent->host)) . $agent->host . pack('n', $agent->port));
                        $agent->status = 2;
                    } else if ($agent->status == 2) {
                        $cli->send($clientData);
                        $agent->status = 3;
                    } else if ($agent->status == 3) {
                        $server->send($fd, $response);
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
                    $agent->data['ip'] = $ip;
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

    /**
     * 是否为内部监控请求
     * @param $headers
     * @return bool
     */
    protected function isLocalRequest($headers)
    {
        foreach ($headers as $header) {
            $header = trim($header);
            if (strpos($header, 'Host:') === 0) {
                $addr = explode(':', str_replace('Host: ', '', $header));
                if (count($addr) == 1) array_push($addr, 80);
                list($domain, $port) = $addr;
                return in_array($domain, $this->localAddress) && $port == $this->port;
            }
        }
        return false;
    }

}