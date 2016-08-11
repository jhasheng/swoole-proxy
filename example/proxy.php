<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/7/18
 * Time: 15:02
 * Email: jhasheng@hotmail.com
 */


require_once __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('swoole') && ini_get('swoole.use_namespace')) {
    $proxy = new \SS\SwooleProxy();
    // 二级代理
//    $proxy->setSocksServer('0.0.0.0:10005');
    $proxy->listen(10004);
} else {
    exit('swoole not loaded' . PHP_EOL);
}
