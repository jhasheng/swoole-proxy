<?php

/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 9/10/2016
 * Time: 11:47 PM
 */
namespace SS\Server;

use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

class Dashboard implements ServerInterface
{
    protected $root = __DIR__ . '/../../stats';

    protected $local = [
        '127.0.0.1',
        '192.168.56.1',
        //        'localhost'
    ];

    protected $route = [
        '/'        => ['template' => 'index.html', 'type' => 'html'],
        '/request' => ['template' => 'request.html', 'type' => 'html'],
        '/view'    => ['type' => 'json'],
    ];

    public function onConnect()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId) = func_get_args();
    }

    public function onReceive()
    {
        /** @var $server \Swoole\Server */
        list($server, $fd, $fromId, $receive) = func_get_args();
        $request = Request\Serializer::fromString($receive);
        $target  = parse_url($request->getRequestTarget());

        $response = $this->getResponse($target['path'], $server->stats());
        $server->send($fd, Response\Serializer::toString($response));
    }

    public function onClose()
    {
        // TODO: Implement onClose() method.
    }

    protected function getResponse($target, $data)
    {
        $header = [
            'X-Powered-By' => 'Swoole Server',
            'Server'       => 'Swoole/' . SWOOLE_VERSION
        ];

        $extension = pathinfo($target, PATHINFO_EXTENSION);
        if (in_array($extension, ['js', 'css', 'ico'])) {
            $filePath = realpath($this->root . '/' . $target);

            switch ($extension) {
                case 'css':
                    $header['Content-Type'] = "text/css; charset=utf-8";
                    break;
                case 'js':
                    $header['Content-Type'] = "text/javascript; charset=utf-8";
                    break;
                case 'ico':
                    $header['Content-Type'] = "image/x-ico; charset=utf-8";
                    break;
                default:
                    $header['Content-Type'] = "text/plain; charset=utf-8";
            }

            if (file_exists($filePath)) {
                $content                  = file_get_contents($filePath);
                $header['Content-Length'] = strlen($content);
                $response                 = new Response('php://temp', 200, $header);
                $response->getBody()->write($content);
                return $response;
            } else {
                $content                  = "404 Not Found";
                $header['Content-Length'] = strlen($content);
                return new Response\HtmlResponse($content, 404, $header);
            }
        } else if (array_key_exists($target, $this->route)) {
            $route = $this->route[$target];

            if ($route['type'] == 'html') {
                $content                  = file_get_contents($this->root . '/' . $route['template']);
                $header['Content-Length'] = strlen($content);
                return new Response\HtmlResponse($content, 200, $header);
            } elseif ($route['type'] == 'json') {
                $header['Content-Length'] = strlen(json_encode($data));
                return new Response\JsonResponse($data, 200, $header);
            } else {
                $content                  = "404 Not Found";
                $header['Content-Length'] = strlen($content);
                return new Response\TextResponse($content, 404, $header);
            }
        } else {
            $content                  = "403 Forbidden";
            $header['Content-Length'] = strlen($content);
            return new Response\TextResponse($content, 403, $header);
        }
    }

}