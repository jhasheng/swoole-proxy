<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/1
 * Time: 13:09
 * Email: jhasheng@hotmail.com
 */

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \SS\MultiServer(
    [
        'main'      => ['host' => '0.0.0.0', 'port' => 10004, 'mode' => SWOOLE_BASE],
        'monitor'   => ['host' => '0.0.0.0', 'port' => 8008],
//        'websocket' => ['host' => '0.0.0.0', 'port' => 10005]
    ]);