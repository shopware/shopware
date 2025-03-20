<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Facade\DiscountFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(DiscountFacade::class)]
#[Package('checkout')]
class DiscountFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $item = new LineItem('foo', 'foo', 'foo');
        $item->setLabel('foo');
        $facade = new DiscountFacade($item);

        static::assertSame('foo', $facade->getId());
        static::assertSame('foo', $facade->getLabel());
    }
}
