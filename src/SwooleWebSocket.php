<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/3
 * Time: 9:50
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class SwooleWebSocket
{
    protected $statsFd = null;

    public function start()
    {
        $ws = new Server('0.0.0.0', 10005);

        $ws->on('open', function(Server $server, $request) {
//            var_dump($request->fd);
            echo "server: handshake success with fd{$request->fd}\n";
        });

        $ws->on('message', function(Server $server, Frame $frame) {
            if (!$this->statsFd && $frame->data == 'stats') {
                foreach ($server->connections as $connection) {
                    if ($connection == $frame->fd) $this->statsFd = $connection;
                }
            }
            foreach ($server->connections as $connection) {
                if ($connection != $frame->fd) $server->push($connection, $frame->data);
            }
        });

        $ws->on('close', function(Server $server) {
//            echo json_encode($server->stats());
//            echo PHP_EOL;
        });

        $ws->on('connect', function(Server $server) {
            echo 'connect';
        });

        $ws->start();
    }
}