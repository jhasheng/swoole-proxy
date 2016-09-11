<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/1
 * Time: 13:50
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use SS\Server\Dashboard;
use SS\Server\HttpProxy;
use SS\Server\WebSocket;
use Swoole\Http\Client;
use Swoole\Server;
use Swoole\WebSocket\Frame;
use Zend\Diactoros\Response;

class MultiServer
{
    /**
     * MultiServer constructor.
     * @param $configs array
     */
    public function __construct($configs)
    {
        $mainConfig = $configs['main'];
        unset($configs['main']);
        $main = new Server($mainConfig['host'], $mainConfig['port'], SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $proxy = new HttpProxy();
        $main->on('start', function (Server $server) use ($proxy) {
            foreach ($server->ports as $port) {
                echo $port->host . ":" . $port->port . PHP_EOL;
            }
        });

        $main->on('connect', [$proxy, 'onConnect']);
        $main->on('receive', [$proxy, 'onReceive']);
        $main->on('close', [$proxy, 'onClose']);


        foreach ($configs as $role => $config) {
            if ($role == 'monitor') {
                $dashboard = new Dashboard();
                $monitor   = $main->addlistener($config['host'], $config['port'], SWOOLE_SOCK_TCP);
                $monitor->on('connect', [$dashboard, 'onConnect']);
                $monitor->on('receive', [$dashboard, 'onReceive']);
                $monitor->on('close', [$dashboard, 'onClose']);
            } else if ($role == 'websocket') {
                $websocket = new WebSocket();
                $transport = $main->addlistener($config['host'], $config['port'], SWOOLE_SOCK_TCP);
                $transport->on('connect', [$websocket, 'onConnect']);
                $transport->on('receive', [$websocket, 'onReceive']);
                $transport->on('close', [$websocket, 'onClose']);
            }
        }

        $main->start();
    }

    protected function transportRequest()
    {

    }

}