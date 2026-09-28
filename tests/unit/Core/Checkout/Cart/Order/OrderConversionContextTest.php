<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderConversionContext::class)]
class OrderConversionContextTest extends TestCase
{
    #[TestDox('defaults to including persistent data')]
    public function testDefault(): void
    {
        static::assertTrue((new OrderConversionContext())->shouldIncludePersistentData());
    }

    /**
     * @deprecated tag:v6.8.0 - remove with the legacy includeOrderDate methods
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyOrderDateMethodsWorkWhenDeprecationEmissionIsDisabled(): void
    {
        $context = new OrderConversionContext();
        $previous = Feature::$emitDeprecations;
        Feature::$emitDeprecations = false;

        try {
            static::assertTrue($context->shouldIncludeOrderDate());
            static::assertSame($context, $context->setIncludeOrderDate(false));
            static::assertFalse($context->shouldIncludeOrderDate());
        } finally {
            Feature::$emitDeprecations = $previous;
        }
    }

    #[TestDox('assign() maps the legacy includeOrderDate option onto includePersistentData')]
    public function testAssignMapsLegacyOrderDateOption(): void
    {
        $context = new OrderConversionContext();
        $context->assign(['includeOrderDate' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
    }

    #[TestDox('assign() keeps includePersistentData when only that option is given')]
    public function testAssignWithPersistentDataOption(): void
    {
        $context = new OrderConversionContext();
        $context->assign(['includePersistentData' => false]);

        static::assertFalse($context->shouldIncludePersistentData());
    }

    #[TestDox('getApiAlias() is cart_order_conversion_context')]
    public function testApiAlias(): void
    {
        static::assertSame('cart_order_conversion_context', (new OrderConversionContext())->getApiAlias());
    }
}
