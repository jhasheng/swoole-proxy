<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/12
 * Time: 9:27
 * Email: jhasheng@hotmail.com
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('swoole') && ini_get('swoole.use_namespace')) {
    $proxy = new \SS\SwooleWebSocket();
    $proxy->start();
} else {
    exit('swoole not loaded' . PHP_EOL);
}