<?php

namespace Chief\Bridge\Psr;

use Chief\ChiefTestCase;
use Illuminate\Container\Container;
use Psr\Container\ContainerInterface;

class PsrContainerTest extends ChiefTestCase
{
    public function testMakeHitsInnerContainer()
    {
        $inner = $this->prophesize(ContainerInterface::class);
        $container = new PsrContainer($inner->reveal());
        $inner->get('stdClass')->shouldBeCalled()->willReturn(new \stdClass());

        $made = $container->make('stdClass');
        $this->assertTrue($made instanceof \stdClass);
    }

    public function testMakeReturnsExpectedInstance()
    {
        $container = new PsrContainer(new Container());
        $made = $container->make('stdClass');
        $this->assertTrue($made instanceof \stdClass);
    }
}