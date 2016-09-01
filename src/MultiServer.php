<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/1
 * Time: 13:50
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Server;

class MultiServer
{

    public function __construct($configs)
    {
        $mainConfig = $configs['main'];
        unset($configs['main']);
        $main = new Server($mainConfig['host'], $mainConfig['port'], $mainConfig['mode'], SWOOLE_SOCK_TCP);

        $main->on('start', function (Server $server) {
            foreach ($server->ports as $port) {
                echo $port->host . ":" . $port->port . PHP_EOL;
            }
        });

        foreach ($configs as $config) {
            $main->addlistener($config['host'], $config['port'], SWOOLE_SOCK_TCP);
        }

        $main->on('connect', [$this, 'onConnect']);
        $main->on('receive', [$this, 'onReceive']);
        $main->on('close', [$this, 'onClose']);
    }

    public function onConnect()
    {
        /** @var $server Server */
        list($server, $fd, $fromId) = func_get_args();
    }

    public function onReceive()
    {
        /** @var $server Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
    }

    public function onClose()
    {
        /** @var $server Server */
        list($server, $fd, $fromId) = func_get_args();
    }
}