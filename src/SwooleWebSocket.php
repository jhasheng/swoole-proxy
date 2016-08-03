<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/3
 * Time: 9:50
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\WebSocket\Server;

class SwooleWebSocket
{
    public static function start()
    {
        $ws = new Server('0.0.0.0', 10005);

        $ws->on('open', function(Server $server, $request) {
            var_dump($request->fd);
        });

        $ws->on('message', function(Server $server, $frame) {
            var_dump($frame);
        });

        $ws->on('close', function(Server $server) {
            var_dump($server);
        });

        $ws->start();
    }
}