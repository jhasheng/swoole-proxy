<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/8
 * Time: 13:24
 * Email: jhasheng@hotmail.com
 */

namespace TLS;

use SS\TlsServer;

require_once __DIR__ . '/../vendor/autoload.php';

if (extension_loaded('swoole') && ini_get('swoole.use_namespace')) {
    (new TlsServer())->listen('0.0.0.0', 9051);
} else {
    exit('swoole not loaded' . PHP_EOL);
}