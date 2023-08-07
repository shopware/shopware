<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Facade\ErrorsFacade;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Facade\ErrorsFacade
 */
class ErrorsFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $facade = new ErrorsFacade(new ErrorCollection());

        $facade->warning('warning');
        $facade->error('error');
        $facade->notice('notice');

        static::assertIsIterable($facade);
        static::assertCount(3, $facade);
        static::assertTrue($facade->has('warning'));
        static::assertTrue($facade->has('error'));
        static::assertTrue($facade->has('notice'));

        $facade->warning('duplicate');
        $facade->warning('duplicate');
        static::assertTrue($facade->has('duplicate'));
        static::assertCount(4, $facade);
        $facade->remove('duplicate');
        static::assertFalse($facade->has('duplicate'));
        static::assertCount(3, $facade);

        static::assertInstanceOf(Error::class, $facade->get('error'));
        static::assertEquals(Error::LEVEL_ERROR, $facade->get('error')->getLevel());
        static::assertInstanceOf(Error::class, $facade->get('warning'));
        static::assertEquals(Error::LEVEL_WARNING, $facade->get('warning')->getLevel());
        static::assertInstanceOf(Error::class, $facade->get('notice'));
        static::assertEquals(Error::LEVEL_NOTICE, $facade->get('notice')->getLevel());
    }
}
