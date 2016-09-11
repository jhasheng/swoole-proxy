<?php

/**
 * Created by PhpStorm.
 * User: krasen
 * Date: 9/10/2016
 * Time: 11:11 PM
 */
namespace SS\Server;

use SS\Agent;

trait AgentTrait
{
    /**
     * @var \SS\Agent[]
     */
    protected $agent = [];

    /**
     * @param \SS\Agent $agent
     * @param $fd
     */
    protected function setAgent(Agent $agent, $fd)
    {
        $this->agent[$fd] = $agent;
    }

    /**
     * @param $fd
     * @return \SS\Agent
     */
    protected function getAgent($fd)
    {
        return $this->agent[$fd];
    }
}