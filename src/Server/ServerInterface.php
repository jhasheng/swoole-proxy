<?php

/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/9/2
 * Time: 14:12
 * Email: jhasheng@hotmail.com
 */
namespace SS\Server;

interface ServerInterface
{
    public function onConnect();
    
    public function onReceive();
    
    public function onClose();
}