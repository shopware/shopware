<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;

/**
 * @internal
 */
#[CoversClass(TaxProviderRegistry::class)]
class TaxProviderRegistryTest extends TestCase
{
    public function testProviderRegistered(): void
    {
        $registry = new TaxProviderRegistry(
            [new TestConstantTaxRateProvider()]
        );

        static::assertTrue($registry->has(TestConstantTaxRateProvider::class));
        static::assertInstanceOf(TestConstantTaxRateProvider::class, $registry->get(TestConstantTaxRateProvider::class));

        static::assertFalse($registry->has(TestEmptyTaxProvider::class));
    }

    public function testProviderNotFound(): void
    {
        $registry = new TaxProviderRegistry(
            [new TestConstantTaxRateProvider()]
        );

        static::assertFalse($registry->has(TestEmptyTaxProvider::class));
        static::assertNull($registry->get(TestEmptyTaxProvider::class));
    }
}
