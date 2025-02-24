<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PriceDefinitionFactory::class)]
class PriceDefinitionFactoryTest extends TestCase
{
    public function testFactoryThrowsAnExceptionWhenTypeIsNotSet(): void
    {
        $factory = new PriceDefinitionFactory();
        $this->expectExceptionObject(CartException::invalidPriceFieldType('none'));
        /** @phpstan-ignore-next-line for test purpose we do not respect array description, type is missing */
        $factory->factory(Context::createDefaultContext(), ['price' => 0.0, 'percentage' => 0.0], 'test');
    }

    public function testFactoryThrowsAnExceptionWhenTypeIsNotSupported(): void
    {
        $factory = new PriceDefinitionFactory();
        $this->expectExceptionObject(CartException::invalidPriceFieldType('unsupported'));
        $factory->factory(Context::createDefaultContext(), ['type' => 'unsupported', 'price' => 0.0, 'percentage' => 0.0], 'test');
    }
}
