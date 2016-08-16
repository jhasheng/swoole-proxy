<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/16
 * Time: 17:08
 * Email: jhasheng@hotmail.com
 */

namespace SS;


class DecodeHandshake
{
    protected $buffer = null;

    /**
     * @var Buffer
     */
    protected $protocolContent = null;

    public function __construct(Buffer $buffer)
    {
        if ($buffer && $buffer->length < 1) {
            throw new \Exception('buffer is empty');
        }
        $this->buffer = $buffer;
    }

    public function decode()
    {
        // 20 change_cipher_spec 21 alert 22 handshake 23 application data
        // http://www.rfc-editor.org/rfc/rfc2246.txt page 16
        $contentType = $this->substr(0, 1);
        // 主版本号 固定值 3
        $majorVersion = $this->substr(0, 1);
        // 与主版本号结合使用 3.0 SSLv3 3.3 SSLv1.2
        $minorVersion = $this->substr(0, 1);

        $contentLength = hexdec($this->substr(0, 2));

        $content = $this->substr(0, $contentLength, false);

        $buffer = new Buffer();
        $buffer->append($content);
        /**
         * rfc5246 page 70
         * hello_request(0), client_hello(1), server_hello(2),
         * certificate(11), server_key_exchange (12),
         * certificate_request(13), server_hello_done(14),
         * certificate_verify(15), client_key_exchange(16),
         * finished(20)
         */
        $handshakeType = $buffer->substr(0, 1);

        $protocolLength = $buffer->substr(0, 3);
        // protocol version
        $protocolVersion = $buffer->substr(0, 2);
    }

    protected function handleClientHello()
    {
        // page 40
        $buffer = $this->protocolContent;
        $data   = [
            // handshake protocol type
            'type'              => $buffer->substr(0, 1)->toDec(),
            'length'            => $buffer->substr(0, 3)->toDec(),
            // version
            'majorver'          => $buffer->substr(0, 1)->toDec(),
            'minorver'          => $buffer->substr(0, 1)->toDec(),
            // struct { uint32 gmt_unix_time; opaque random_bytes[28]; } Random;
            'randomGUT'         => $buffer->substr(0, 4)->toDec(),
            'randomBytes'       => $buffer->substr(0, 28)->toDec(),
            // opaque SessionID<0..32>;
            'sid'               => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toDec(),
            // uint8 CipherSuite[2];
            'cipherSuites'      => $buffer->substr(0, $buffer->substr(0, 2)->toHex())->toHex(),
            // enum { null(0), (255) } CompressionMethod;
            'compressionMethod' => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
        ];
        $buffer->clear();
        return $data;
    }

    protected function handleServerHello()
    {
        // page 40
        $buffer = $this->protocolContent;
        $data   = [
            // handshake protocol type
            'type'              => $buffer->substr(0, 1)->toDec(),
            'length'            => $buffer->substr(0, 3)->toDec(),
            // version
            'majorver'          => $buffer->substr(0, 1)->toDec(),
            'minorver'          => $buffer->substr(0, 1)->toDec(),
            // struct { uint32 gmt_unix_time; opaque random_bytes[28]; } Random;
            'randomGUT'         => $buffer->substr(0, 4)->toDec(),
            'randomBytes'       => $buffer->substr(0, 28)->toDec(),
            // opaque SessionID<0..32>;
            'sid'               => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toDec(),
            // uint8 CipherSuite[2];
            'cipherSuites'      => $buffer->substr(0, 2)->toHex(),
            // enum { null(0), (255) } CompressionMethod;
            'compressionMethod' => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
        ];
        $buffer->clear();
        return $data;
    }

    protected function handleCertificate()
    {
        // page 40
        $buffer = $this->protocolContent;
        $data   = [
            // handshake protocol type
            'type'              => $buffer->substr(0, 1)->toDec(),
            'length'            => $buffer->substr(0, 3)->toDec(),
            'certificatesLength' => $buffer->substr(0, 3)->toDec(),
        ];
        $buffer->clear();
        return $data;
    }


    protected function handleAlertProtocol(Buffer $buffer)
    {

    }


    protected function substr($offset, $length, $dec = true)
    {
        if ($dec) {
            return bin2hex($this->buffer->substr($offset, $length, true));
        }
        return $this->buffer->substr($offset, $length, true);
    }

}