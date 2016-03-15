<?php

namespace Chief\Decorators;

use Chief\Cache;
use Chief\CacheableCommand;
use Chief\Command;
use Chief\CommandBus;
use Chief\Decorator;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class CachingDecoratorTest extends DecoratorTest
{
    /** @var ObjectProphecy */
    private $cache;

    public function test_can_be_instantiated_and_implements_decorator()
    {
        $decorator = $this->getDecorator();
        $decorator->setInnerBus($this->prophesize(CommandBus::class)->reveal());
        $this->assertInstanceOf(CommandBus::class, $decorator);
        $this->assertInstanceOf(Decorator::class, $decorator);
    }

    public function test_commands_not_implementing_cacheable_are_ignored()
    {
        $decorator = $this->getDecorator();
        $inner = $this->prophesize(CommandBus::class);
        $decorator->setInnerBus($inner->reveal());

        $command = $this->prophesize(Command::class)->reveal();

        $this->cache->save(Argument::any())->shouldNotBeCalled();
        $this->cache->getItem(Argument::any())->shouldNotBeCalled();

        $inner->execute($command)->shouldBeCalled()->willReturn(7);

        $result = $decorator->execute($command);
        $this->assertEquals(7, $result);
    }

    public function test_execution_return_value_is_cached_when_command_implements_cacheable()
    {
        $decorator = $this->getDecorator();
        $inner = $this->prophesize(CommandBus::class);
        $decorator->setInnerBus($inner->reveal());

        $command = $this->prophesize(CacheableCommand::class)->reveal();

        $notCachedItem = $this->prophesize(CacheItemInterface::class);
        $notCachedItem->isHit()->willReturn(false);
        $notCachedItem->getKey()->willReturn(md5(serialize(($command))));
        $this->cache->getItem(Argument::any())->willReturn($notCachedItem->reveal());

        $notCachedItem->set(7)->shouldBeCalled()->willReturn($notCachedItem->reveal());
        $notCachedItem->expiresAfter(3600)->shouldBeCalled()->willReturn($notCachedItem->reveal());

        $this->cache->save(Argument::that(function (CacheItemInterface $item) use ($command) {
            return $item->getKey() === md5(serialize($command));
        }))->shouldBeCalled();

        $inner->execute($command)->shouldBeCalled()->willReturn(7);

        $result = $decorator->execute($command);
        $this->assertEquals(7, $result);
    }

    public function test_cache_item_value_is_returned_if_cached()
    {
        $decorator = $this->getDecorator();
        $inner = $this->prophesize(CommandBus::class);
        $decorator->setInnerBus($inner->reveal());

        $command = $this->prophesize(CacheableCommand::class)->reveal();

        $cachedItem = $this->prophesize(CacheItemInterface::class);
        $cachedItem->isHit()->willReturn(true);
        $cachedItem->get()->willReturn('foo');
        $this->cache->getItem(Argument::any())->willReturn($cachedItem->reveal());
        $this->cache->save(Argument::any())->shouldNotBeCalled();

        $inner->execute($command)->shouldNotBeCalled();
        $result = $decorator->execute($command);
        $this->assertEquals('foo', $result);
    }

    /**
     * @return \Chief\Decorator
     */
    protected function getDecorator()
    {
        $this->cache = $this->prophesize(CacheItemPoolInterface::class);
        return new CachingDecorator($this->cache->reveal());
    }
}
