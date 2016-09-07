<?php

/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/2
 * Time: 14:12
 * Email: jhasheng@hotmail.com
 */
namespace SS\Server;

use GuzzleHttp\Promise\Promise;
use SS\Agent;
use Swoole\Client;
use Swoole\Server;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

class HttpProxy implements ServerInterface
{
    /**
     * @var Agent[]
     */
    protected $agent = [];

    public function onConnect()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        $this->agent[$fd] = new Agent();
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        $agent = $this->agent[$fd];

        if ($agent->remote) {
            $agent->remote->send($receive);
        } else {
            $this->handleRequest($server, $fd, $receive, function ($status, $request) {
                if (20 == $status && $request instanceof Request) {
                    $host = array_pop($request->getHeader('host'));
                    $info = explode(':', $host);
                }
            });
//            if (!$agent->https) {
//                return $this->handleConnect($server, $fd, $receive);
//            }
//            $addr = explode(':', array_pop($agent->request->getHeader('host')));
//            $host = $addr[0];
//            $port = isset($addr[1]) ? $addr[1] : 80;
//

        }
    }

    public function onClose()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        echo 'close' . $fd . PHP_EOL;
    }

    protected function initRemote(Server $server, $receive, $fd, $host, $port)
    {
        $agent = $this->agent[$fd];

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

        });

        $remote->on('close', function (Client $client) {

        });

        swoole_async_dns_lookup($host, function ($host, $ip) use ($remote, $port) {
            $remote->connect($ip, $port);
        });
    }

    /**
     * @param Server $server
     * @param integer $fd
     * @param string $receive
     * @param callable $next
     * @return bool
     */
    protected function handleRequest(Server $server, $fd, $receive, callable $next)
    {
        try {
            $request = Request\Serializer::fromString($receive);
            $host = array_pop($request->getHeader('host'));
            $info = explode(':', $host);
            if ($request->getMethod() != 'CONNECT') {
                $this->initRemote($server, $receive, $fd, $info[0], $info[1] ?: 80);
                return $next(20, $request);
            }
            $response = new Response('php://temp', 200, ['User-Agent' => 'Swoole Proxy Server']);
            $response->getBody()->write('welcome');
            $this->initRemote($server, $receive, $fd, $info[0], $info[1] ?: 80);
            return $next($server->send($fd, Response\Serializer::toString($response)), $request);
        } catch (\Exception $e) {
            return $next(21, $e->getMessage());
        }
    }
}