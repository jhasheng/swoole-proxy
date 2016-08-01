<?php
/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 7/23/2016
 * Time: 7:32 PM
 */

$ch = curl_init('www.oschina.net');

curl_setopt($ch, CURLOPT_PROXY, '192.168.56.1:8008');
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, fopen('./headers.txt', 'w'));
curl_exec($ch);
