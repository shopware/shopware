<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestGenericExceptionTaxProvider;

/**
 * @internal
 */
class TaxProviderRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TaxProviderRegistry $taxProviderRegistry;

    protected function setUp(): void
    {
        $this->taxProviderRegistry = $this->getContainer()->get(TaxProviderRegistry::class);
    }

    public function testTestProvidersAreRegisteredByServiceContainerTag(): void
    {
        static::assertTrue($this->taxProviderRegistry->has(TestConstantTaxRateProvider::class));
        static::assertInstanceOf(TestConstantTaxRateProvider::class, $this->taxProviderRegistry->get(TestConstantTaxRateProvider::class));

        static::assertTrue($this->taxProviderRegistry->has(TestGenericExceptionTaxProvider::class));
        static::assertInstanceOf(TestGenericExceptionTaxProvider::class, $this->taxProviderRegistry->get(TestGenericExceptionTaxProvider::class));

        static::assertTrue($this->taxProviderRegistry->has(TestEmptyTaxProvider::class));
        static::assertInstanceOf(TestEmptyTaxProvider::class, $this->taxProviderRegistry->get(TestEmptyTaxProvider::class));

        static::assertFalse($this->taxProviderRegistry->has('foo'));
        static::assertNull($this->taxProviderRegistry->get('foo'));
    }
}
