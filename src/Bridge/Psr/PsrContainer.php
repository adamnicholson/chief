<?php

namespace Chief\Bridge\Psr;

use Chief\Container;

class PsrContainer implements Container
{
    public function __construct(\Psr\Container\ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function make($class)
    {
        return $this->container->get($class);
    }
}
