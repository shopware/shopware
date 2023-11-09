<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\DiscountFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Facade\DiscountFacade
 */
class DiscountFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $item = new LineItem('foo', 'foo', 'foo');
        $item->setLabel('foo');
        $facade = new DiscountFacade($item);

        static::assertEquals('foo', $facade->getId());
        static::assertEquals('foo', $facade->getLabel());
    }
}
