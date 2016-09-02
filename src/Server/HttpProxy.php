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
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        if ($this->agent[$fd]) {
            $remote = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

            $remote->on('connect', function(Client $client) use ($receive, $fd, $remote) {
                $client->send($receive);
                $this->agent[$fd]['remote'] = $remote;
            });

            $remote->on('receive', function(Client $client, $recv) use($server, $fd) {
                $server->send($fd, $recv);
            });

            $remote->on('error', function(Client $client) {

            });

            $remote->on('close', function(Client $client) {

            });
        } else {
            $this->agent[$fd]['remote']->send($fd, $receive);
        }
    }

    public function onClose()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
    }
}