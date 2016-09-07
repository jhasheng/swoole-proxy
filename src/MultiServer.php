<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/1
 * Time: 13:50
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use SS\Server\HttpProxy;
use Swoole\Server;

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

        $main->on('start', function (Server $server) {
            foreach ($server->ports as $port) {
                echo $port->host . ":" . $port->port . PHP_EOL;
            }
        });

//        foreach ($configs as $config) {
//            $main->addlistener($config['host'], $config['port'], SWOOLE_SOCK_TCP);
//        }
        
        $proxy = new HttpProxy();

        $main->on('connect', [$proxy, 'onConnect']);
        $main->on('receive', [$proxy, 'onReceive']);
        $main->on('close', [$proxy, 'onClose']);

        $main->start();
    }

}