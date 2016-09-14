<?php

/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 9/11/2016
 * Time: 2:38 AM
 */
namespace SS\Server;

use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

class WebSocket
{
    const MAGIC_STRING = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

    protected $backends = [];

    public function onConnect()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        if (!isset($this->backends[$fd])) {
            $backend             = new \stdClass();
            $backend->fd         = $fd;
            $backend->handshake  = false;
            $backend->opcode     = -1;
            $backend->data       = null;
            $backend->except     = false;
            $this->backends[$fd] = $backend;
        }
        echo sprintf("new client [%s] join" . PHP_EOL, $fd);
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        $backend = $this->backends[$fd];
        echo $fd . PHP_EOL;
        if (!$backend) $server->close($fd);
        // echo bin2hex($receive) . PHP_EOL;
        if ($backend->handshake) {
            $decode = $this->unwrap($receive);
            if ('stat' == $decode['content']) {
                $backend->except = true;
            }
            foreach ($this->backends as $backend) {
                if ($backend->except) continue;
                $server->send($backend->fd, $this->wrap($decode['content']));
            }
        } else {
            preg_match('#Sec-WebSocket-Key:\s(?<key>[^\s].*?[^\s])#Ui', $receive, $matches);
            $key       = $matches['key'];
            $acceptKey = base64_encode(sha1($key . self::MAGIC_STRING, true));
            $header    = [
                "HTTP/1.1 101 Switching Protocols",
                "Upgrade: websocket",
                "Connection: Upgrade",
                "Sec-WebSocket-Accept: {$acceptKey}"
            ];

            $response           = implode("\r\n", $header) . "\r\n\r\n";
            $backend->handshake = true;
            echo sprintf("client [%s] handshake success" . PHP_EOL, $fd);
            $server->send($fd, $response);
        }
    }

    public function onClose()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        echo sprintf("client %s be closed" . PHP_EOL, $fd);
        unset($this->backends[$fd]);
    }

    public function encode($msg)
    {
        $frame    = [];
        $frame[0] = '81';
        $len      = strlen($msg);
        $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
        $frame[2] = null;
        for ($i = 0, $l = strlen($msg); $i < $l; $i++) {
            $frame[2] .= dechex(ord($msg{$i}));
        }

        $data = implode('', $frame);
        return pack("H*", $data);
    }

    protected function wrap($message, $opcode = 0x1, $end = true, $mask = false)
    {
        $fin = $end ? 0x1 : 0x0;

        $data = chr(($fin << 7) | $opcode);

        $length = strlen($message);

        if ($length <= 0x7d) {
            $data .= chr((0x0 << 7) | $length);
        } else if ($length >= 0x7f && $length <= 0xffff) {
            $data .= chr((0x0 << 7) | 0x7e) . pack('n', $length);
        } else if ($length > 0xffff && $length <= 0xffffffff) {
            $data .= chr((0x0 << 7) | 0x7f) . pack('NN', 0, $length);
        } else {

        }
        return $data . $message;
    }

    protected function unwrap($string)
    {
        $fin     = ord(substr($string, 0, 1)) >> 7;
        $opcode  = ord(substr($string, 0, 1)) & 0x0f;
        $isMask  = ord(substr($string, 1, 1)) >> 7;
        $payload = ord(substr($string, 1, 1)) & 0x7f;

        $offset = $length = 0;
        if ($payload == 126) { // 0x7e
            // 读取接下来的 16 位并转换为无符号整数，并作为长度。
            $length = hexdec(bin2hex(substr($string, 2, 2)));
            $offset = 4;
        } else if ($payload == 127) { // 0x7f
            // 读取接下来的 64 位并转换为无符号整数 (最高有效位必须为0)，并作为长度。
            $length = hexdec(bin2hex(substr($string, 2, 8)));
            $offset = 10;
        } else if ($payload < 126) {
            // 长度为当前值
            $length = $payload;
            $offset = 2;
        } else {
            //return false;
        }

//        echo sprintf("client: FIN = %s, opcode = %s, mask = %s, length = %s" . PHP_EOL, $fin, $opcode, $isMask, $payload);

        if ($opcode == 0x00) {
            return ['opcode' => $opcode];
        }

        if ($isMask) {
            $decode = "";
            $mask = substr($string, $offset, 4);
            $offset += 4;
            $data = substr($string, $offset, $length);
            for ($i = 0, $l = strlen($data); $i < $l; $i++) {
                $decode .= chr(ord($data[$i]) ^ ord($mask[$i % 4]));
            }
        } else {
            $data = substr($string, $offset, $length);
            $decode = $data;
        }
        return ['opcode' => $opcode, 'content' => $decode];
    }

}