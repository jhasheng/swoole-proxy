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

    public function __construct($string)
    {
        if ($string && strlen($string) < 1) {
            throw new \Exception('empty');
        }
        $buffer = new Buffer();
        $buffer->append($string);
        $this->buffer = $buffer;
    }

    public function decode()
    {
        $decodeData = [];
        while (!$this->isLast()) {
            $buffer = $this->buffer;
            $main   = [
                // 20 change_cipher_spec 21 alert 22 handshake 23 application data
                // http://www.rfc-editor.org/rfc/rfc2246.txt page 16
                'type'   => $buffer->substr(0, 1)->toDec(),
                // 主版本号 固定值 3
                'major'  => $buffer->substr(0, 1)->toDec(),
                // 与主版本号结合使用 3.0 SSLv3 3.3 SSLv1.2
                'minor'  => $buffer->substr(0, 1)->toDec(),
                'length' => $buffer->substr(0, 2)->toDec(),
            ];
            echo $buffer->substr(0, -1, false)->toHex() . PHP_EOL;
            /**
             * rfc5246 page 70
             * hello_request(0), client_hello(1), server_hello(2),
             * certificate(11), server_key_exchange (12),
             * certificate_request(13), server_hello_done(14),
             * certificate_verify(15), client_key_exchange(16),
             * finished(20)
             */
            $handshakeType = $buffer->substr(0, 1, false)->toDec();
            switch ($handshakeType) {
                case 1:
                    $data = $this->handleClientHello();
                    break;
                case 2:
                    $data = $this->handleServerHello();
                    break;
                case 4:
                    $data = $this->handleTicket();
                    break;
                case 11:
                    $data = $this->handleCertificate();
                    break;
                case 12:
                    $data = $this->handleServerExchange();
                    break;
                case 16:
                    $data = $this->handleClientExchange();
                    break;
                case 13:
                case 14:
                case 15:
                case 20:
                    throw new \Exception(sprintf("handshake type %d not support yet!", $handshakeType));
                default:
                    throw new \Exception(sprintf("handshake type %d is invalid!", $handshakeType));
            }
            $data['main'] = $main;
            $decodeData[] = $data;
        }
        return $decodeData;
    }

    protected function handleClientHello()
    {
        // page 40
        $buffer = $this->buffer;
        $data   = [
            // handshake protocol type
            'type'              => $buffer->substr(0, 1)->toDec(),
            'length'            => $buffer->substr(0, 3)->toDec(),
            // version
            'majorver'          => $buffer->substr(0, 1)->toDec(),
            'minorver'          => $buffer->substr(0, 1)->toDec(),
            // struct { uint32 gmt_unix_time; opaque random_bytes[28]; } Random;
            'randomGUT'         => $buffer->substr(0, 4)->toHex(),
            'randomBytes'       => $buffer->substr(0, 28)->toHex(),
            // opaque SessionID<0..32>;
            'sid'               => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toDec(),
            // uint8 CipherSuite[2];
            'cipherSuites'      => chunk_split($buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(), 4, '|'),
            // enum { null(0), (255) } CompressionMethod;
            'compressionMethod' => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),

            'extension' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(),
            'last'      => $buffer->length
        ];
        return $data;
    }

    protected function handleServerHello()
    {
        // page 40
        $buffer = $this->buffer;
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
        $buffer = $this->buffer;
        $data   = [
            // handshake protocol type
            'type'                => $buffer->substr(0, 1)->toDec(),
            'length'              => $buffer->substr(0, 3)->toDec(),
            'certificatesContent' => $buffer->substr(0, $buffer->substr(0, 3)->toDec())->toHex(),
        ];
        $buffer->clear();
        return $data;
    }

    protected function handleServerExchange()
    {
        $buffer = $this->buffer;
        $data = [
            'type'            => $buffer->substr(0, 1)->toDec(),
            'length'          => $buffer->substr(0, 3)->toDec(),
            'CurveType'       => $buffer->substr(0, 1)->toDec(),
            'Name'            => $buffer->substr(0, 2)->toHex(),
            'Pubkey'          => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
            'Hash'            => $buffer->substr(0, 1)->toHex(),
            'Signature'       => $buffer->substr(0, 1)->toHex(),
            'SignatureString' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex()
        ];
        return $data;
    }

    protected function handleClientExchange()
    {
        $buffer = $this->buffer;
        $data = [
            'type'            => $buffer->substr(0, 1)->toDec(),
            'length'          => $buffer->substr(0, 3)->toDec(),
            'Pubkey'          => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
        ];
        return $data;
    }

    protected function handleTicket()
    {
        $buffer = $this->buffer;

        $data = [
            'type'   => $buffer->substr(0, 1)->toDec(),
            'length' => $buffer->substr(0, 3)->toDec(),
            'Hint'   => $buffer->substr(0, 3)->toDec(),
            'Ticket' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(),
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

    protected function isLast()
    {
        return $this->buffer->length == 0;
    }

}