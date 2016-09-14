<?php

/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/2
 * Time: 14:12
 * Email: jhasheng@hotmail.com
 */
namespace SS\Server;

use SS\Agent;
use Swoole\Client;
use Swoole\Server;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

class HttpProxy implements ServerInterface
{
    use AgentTrait;

    /**
     * @var \Swoole\Http\Client
     */
    public $websocket = null;

    public function onConnect()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        $this->setAgent(new Agent(), $fd);

        if (!$this->websocket) {
            $websocket = new \Swoole\Http\Client('0.0.0.0', 10007);
            $websocket->on('message', function (\Swoole\Http\Client $client, $frame) {});
            $websocket->upgrade('/', function (\Swoole\Http\Client $client) use ($websocket) {
                $client->push('stat');
                $this->websocket = $websocket;
            });
        }
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        $agent = $this->getAgent($fd);

        if ($agent->remote) {
            $agent->remote->send($receive);
        } else {
            $this->handleRequest($server, $fd, $receive, function ($status, $request) {
//                if (20 == $status && $request instanceof Request) {
//                    $host = array_pop($request->getHeader('host'));
//                    $info = explode(':', $host);
//                }
            });
        }
    }

    public function onClose()
    {
        /** @var $server \Swoole\Server */
//        list($server, $fd, $fromId) = func_get_args();
//        echo 'close ==> ' . $fd . PHP_EOL;
    }

    /**
     * @param Server $server
     * @param string $receive
     * @param integer $fd
     */
    protected function initRemote(Server $server, $receive, $fd)
    {
        $agent = $this->getAgent($fd);

        $remote = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $remote->on('connect', function (Client $client) use ($receive, $remote, $agent) {
            $client->send($receive);
            if ($agent->https)
                $agent->remote = $remote;
        });

        $remote->on('receive', function (Client $client, $recv) use ($server, $fd, $agent) {
            $server->send($fd, $recv);
        });

        $remote->on('error', function (Client $client) {
            echo sprintf("client %s error" . PHP_EOL, swoole_strerror($client->errCode));
        });

        $remote->on('close', function (Client $client) {
//            echo sprintf("client close") . PHP_EOL;
        });

        swoole_async_dns_lookup($agent->host, function ($host, $ip) use ($remote, $agent) {
            echo sprintf("[%s] ==> %s" . PHP_EOL, $host, $ip);
            $remote->connect($ip, $agent->port);
        });
    }

    /**
     * 解析HTTP请求头及HTTPS CONNECT 请求
     *
     * @param Server $server
     * @param integer $fd
     * @param string $receive
     * @param callable $next
     * @return mixed
     */
    protected function handleRequest(Server $server, $fd, $receive, callable $next)
    {
        try {
            $agent   = $this->getAgent($fd);
            $request = Request\Serializer::fromString($receive);

            $host    = $request->getHeader('host');

//            echo "host ===> " . $request->getRequestTarget() . PHP_EOL;
            $headers = $request->getHeaders();
            $headers['method'] = $request->getMethod();
            $headers['status'] = 200;
            $headers['url'] = $request->getRequestTarget();
            if ($this->websocket instanceof \Swoole\Http\Client) {
                $this->websocket && $this->websocket->push(json_encode($headers));
            } else {
                var_dump($this->websocket);
                echo PHP_EOL;
            }

            $info        = explode(':', $host[0]);
            $agent->host = $info[0];
            $agent->port = isset($info[1]) ? $info[1] : 80;
            if ($request->getMethod() != 'CONNECT') {
                $this->initRemote($server, $receive, $fd);
                return $next(20, $request);
            }
            $response = new Response('php://temp', 200, ['User-Agent' => 'Swoole Server']);
            $response->getBody()->write('welcome');
            $this->initRemote($server, $receive, $fd);
            return $next($server->send($fd, Response\Serializer::toString($response)), $request);
        } catch (\Exception $e) {
            return $next(21, $e->getMessage());
        }
    }

}