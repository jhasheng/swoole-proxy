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
        $sn = 0;
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

            if ($main['type'] == 23) {
                $data = [
                    'title' => 'application data',
                    'data' => $buffer->substr(0, $main['length'])->toHex()
                ];
                $data['main'] = $main;
                $data['sn'] = $sn;
                $decodeData[] = $data;
                $sn++;
                continue;
            }

            if ($main['type'] == 20) {
                $data = [
                    'title' => 'change chipher spec',
                    'data' => $buffer->substr(0, $main['length'])->toHex()
                ];
                $data['main'] = $main;
                $data['sn'] = $sn;
                $decodeData[] = $data;
                $sn++;
                continue;
            }

            if (!in_array($main['type'], [21, 22])) {
                $data = ['title' => 'invalid type => ' . $main['type'], '_' => $buffer->substr(0, -1)->toHex()];
                $data['main'] = $main;
                $data['sn'] = $sn;
                $decodeData[] = $data;
                return $decodeData;
            }

//            echo $buffer->substr(0, 1, false)->toDec() . PHP_EOL;
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
                case 0:
                    $data = $this->handleHelloRequest();
                    break;
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
                case 14:
                    $data = $this->handleServerHelloDone();
                    break;
                case 16:
                    $data = $this->handleClientExchange();
                    break;
                case 13:
                case 15:
                case 20:
                default:
                    $data = $this->handleUnknow();
            }
            $data['main'] = $main;
            $data['sn'] = $sn;
            $decodeData[] = $data;
            $sn++;
        }
        return $decodeData;
    }

    protected function handleHelloRequest()
    {
        $buffer = $this->buffer;
        $data   = [
            'title'  => 'hello request',
            'type'   => $buffer->substr(0, 1)->toDec(),
            'length' => $buffer->substr(0, 3)->toDec(),
            'last'   => $buffer->substr(0, -1)->toHex()
        ];
        return $data;
    }
    protected function handleClientHello()
    {
        // page 40
        $buffer = $this->buffer;
        $data   = [
            // handshake protocol type
            'title'             => 'client hello',
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
            'title'             => 'server hello',
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
            'cipherSuites'      => $buffer->substr(0, 2)->toHex(),
            // enum { null(0), (255) } CompressionMethod;
            'compressionMethod' => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),

            'extension' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(),
            'last'      => $buffer->length
        ];
        return $data;
    }

    protected function handleCertificate()
    {
        // page 40
        $buffer = $this->buffer;
        $data   = [
            'title'               => 'ceritficate',
            // handshake protocol type
            'type'                => $buffer->substr(0, 1)->toDec(),
            'length'              => $buffer->substr(0, 3)->toDec(),
            'certificatesContent' => $this->parseCertificateChian($buffer->substr(0, $buffer->substr(0, 3)->toDec())->toString()),
            'last'                => $buffer->length
        ];
        return $data;
    }

    protected function handleServerExchange()
    {
        $buffer = $this->buffer;
        $data = [
            'title'           => 'server key exchange',
            'type'            => $buffer->substr(0, 1)->toDec(),
            'length'          => $buffer->substr(0, 3)->toDec(),
            'CurveType'       => $buffer->substr(0, 1)->toDec(),
            'Name'            => $buffer->substr(0, 2)->toHex(),
            'KeyL'            => $buffer->substr(0, 1, false)->toDec(),
            'Pubkey'          => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
//            'Signature'       => $buffer->substr(0, 2)->toHex(),
            'SignLenth'       => $buffer->substr(0, 2, false)->toHex(),
            'SignatureString' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(),
            'last'            => $buffer->length
        ];
        return $data;
    }

    protected function handleClientExchange()
    {
        $buffer = $this->buffer;
        $data   = [
            'title'  => 'client key exchange',
            'type'   => $buffer->substr(0, 1)->toDec(),
            'data'   => $buffer->substr(0, $buffer->substr(0, 3)->toDec())->toHex(),
//            'length' => $buffer->substr(0, 3)->toDec(),
//            'Pubkey' => $buffer->substr(0, $buffer->substr(0, 1)->toDec())->toHex(),
            'last'   => $buffer->length
        ];
        return $data;
    }

    protected function handleServerHelloDone()
    {
        $buffer = $this->buffer;
        $data   = [
            'title'  => 'server hello done',
            'type'   => $buffer->substr(0, 1)->toDec(),
            'length' => $buffer->substr(0, 3)->toDec(),
            'last'      => $buffer->length
        ];
        return $data;
    }

    protected function handleTicket()
    {
        $buffer = $this->buffer;

        $data = [
            'title'  => 'ticket',
            'type'   => $buffer->substr(0, 1)->toDec(),
            'length' => $buffer->substr(0, 3)->toDec(),
            'Hint'   => $buffer->substr(0, 4)->toDec(),
            'Ticket' => $buffer->substr(0, $buffer->substr(0, 2)->toDec())->toHex(),
            'last'   => $buffer->length
        ];
        return $data;
    }

    protected function handleChangeCipherSpec()
    {
        $buffer = $this->buffer;
        $data = [
            'title'  => 'change cipher spec',
            'data'   => $buffer->substr(0, 1)->toDec(),
            'last'      => $buffer->length,
            'last_data' => $buffer->substr(0, -1, false)->toHex()
        ];
        return $data;
    }

    protected function handleUnknow()
    {
        $buffer = $this->buffer;
        $data = [
            'title'  => 'unknow',
            '_'   => $buffer->substr(0, $buffer->length)->toHex(),
            'last' => $buffer->length
        ];
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

    protected function der2pem($data)
    {
        return sprintf("-----BEGIN CERTIFICATE-----\n%s-----END CERTIFICATE-----\n", chunk_split(base64_encode($data), 64, "\n"));
    }

    protected function parseCertificateChian($cert)
    {
        $buffer = new Buffer();
        $buffer->append($cert);
        $certs = [];
        while ($buffer->length > 0) {
            $content = $buffer->substr(0, $buffer->substr(0, 3)->toDec())->toString();
//            var_dump(openssl_x509_parse($this->der2pem($content)));
            array_push($certs, base64_encode($content));
        }
        return $certs;
    }
}