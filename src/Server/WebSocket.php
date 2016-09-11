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
    const WS_MASK = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

    protected $backends = [];

    public function onConnect()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
        $backend             = new \stdClass();
        $backend->handshake  = false;
        $backend->isStat     = false;
        $backend->fd         = $fd;
        $this->backends[$fd] = $backend;
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        $backend = $this->backends[$fd];
//        echo $receive;
        if (!$backend->handshake) {
            $request = Request\Serializer::fromString($receive);
            if ($request->getMethod() == 'CONNECT') {
                $data = new Response('php://temp', 200);
            } else {
                echo sprintf("client %s handshake success" . PHP_EOL, $fd);
//                echo $receive . PHP_EOL;
                $key = array_pop($request->getHeader('Sec-WebSocket-Key'));
                $key .= self::WS_MASK;
                $response = new Response('php://temp', 101);
                $response->getBody()->write(chr(0));
                $data = $response->withHeader('Upgrade', 'websocket')->withHeader('Connection', 'Upgrade')->withHeader('Sec-WebSocket-Accept', base64_encode(sha1($key, true)));
                $backend->handshake = true;
            }
            $server->send($fd, Response\Serializer::toString($data));
        } else {
            $data = $this->handleData($receive);
            echo $data . PHP_EOL;
            if ($data == 'register') {
                $backend->isStat = true;
            }

//            foreach ($this->backends as $backend) {
//                if (!$backend->isStat) continue;
//                echo bin2hex($receive) . PHP_EOL;
//                $server->send($backend->fd, "\x81\x01\x97");
//            }
//            if (false === $data) {
//                echo $fd . " ==> " . ($receive) . PHP_EOL;
//                $server->close($fd);
//            } else {
//                if ('stat' == $data) $backend->isStat = true;
//                foreach ($this->backends as $backend) {
//                    if ($backend->isStat) continue;
//                    $data = $this->wrap($data);
//                    $server->send($backend->fd, "\x81\x01\x97");
//                    echo bin2hex($data) . PHP_EOL;
//                    $server->send($backend->fd, $data);
//                }
//            }
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

    public function handleData($data)
    {
        $offset = 0;

        $temp   = ord($data[$offset++]);
        $FIN    = ($temp >> 7) & 0x1;
        $RSV1   = ($temp >> 6) & 0x1;
        $RSV2   = ($temp >> 5) & 0x1;
        $RSV3   = ($temp >> 4) & 0x1;
        $opcode = $temp & 0xf;

//        echo "First byte: FIN is $FIN, RSV1-3 are $RSV1, $RSV2, $RSV3; Opcode is $opcode \n";
//        if (0x08 == $opcode) return false;

        $temp           = ord($data[$offset++]);
        $mask           = ($temp >> 7) & 0x1;
        $payload_length = $temp & 0x7f;
        if ($payload_length == 126) {
            $temp = substr($data, $offset, 2);
            $offset += 2;
            $temp           = unpack('nl', $temp);
            $payload_length = $temp['l'];
        } elseif ($payload_length == 127) {
            $temp = substr($data, $offset, 8);
            $offset += 8;
            $temp           = unpack('nl', $temp);
            $payload_length = $temp['l'];
        }
//        echo "mask is $mask, payload_length is $payload_length \n";

        if ($mask == 0) {
            $temp    = substr($data, $offset);
            $content = '';
            for ($i = 0; $i < $payload_length; $i++) {
                $content .= $temp[$i];
            }
        } else {
            $masking_key = substr($data, $offset, 4);
            $offset += 4;

            $temp    = substr($data, $offset);
            $content = '';
            for ($i = 0; $i < $payload_length; $i++) {
                $content .= chr(ord($temp[$i]) ^ ord($masking_key[$i % 4]));
            }
        }

//        echo "content is $content \n";
        return $content;
    }

    protected function wrap($msg = "", $opcode = 0x1)
    {
        //默认控制帧为0x1（文本数据）
        $firstByte  = 0x80 | $opcode;
        $encodedata = null;
        $len        = strlen($msg);

        if (0 <= $len && $len <= 125) {
            $encodedata = chr(0x81) . chr($len) . $msg;
        } else if (126 <= $len && $len <= 0xFFFF) {
            $low        = $len & 0x00FF;
            $high       = ($len & 0xFF00) >> 8;
            $encodedata = chr($firstByte) . chr(0x7E) . chr($high) . chr($low) . $msg;
        }

        return $encodedata;
    }

}