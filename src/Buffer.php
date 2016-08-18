<?php
/**
 * Created by PhpStorm.
 * User: Krasen
 * Date: 16/8/16
 * Time: 10:29
 * Email: jhasheng@hotmail.com
 */

namespace SS;


use Swoole\Buffer as BaseBuffer;

class Buffer extends BaseBuffer
{
    protected $tmp = null;

    public function substr($offset, $length = -1, $remove = true)
    {
        $this->tmp = parent::substr($offset, $length, $remove);
        return $this;
    }

    public function toHex()
    {
        return bin2hex($this->tmp);
    }

    public function toDec()
    {
        return hexdec($this->toHex());
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->tmp;
    }
}