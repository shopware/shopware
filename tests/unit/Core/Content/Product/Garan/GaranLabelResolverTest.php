<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelResolver::class)]
class GaranLabelResolverTest extends TestCase
{
    public function testResolvesToNullWhenGuaranteeNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($product));
    }

    public function testResolvesToNullWhenManufacturerNumberMissing(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: null, guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($product));
    }

    public function testResolvesToNullWhenDurationInvalid(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 12);

        static::assertNull($this->createResolver()->resolve($product));
    }

    public function testResolvesToNullWhenManufacturerNameMissing(): void
    {
        $product = $this->createProduct(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: $this->createManufacturer(name: null, translatedName: null)
        );

        static::assertNull($this->createResolver()->resolve($product));
    }

    public function testResolvesLabelWhenManufacturerNameIsOnlyAvailableThroughTranslationFallback(): void
    {
        $product = $this->createProduct(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: $this->createManufacturer(name: null, translatedName: 'ACME')
        );

        $svg = $this->createResolver()->resolve($product);

        static::assertIsString($svg);
        static::assertStringContainsString('ACME', $svg);
    }

    public function testResolvesNestedLabelWhenManufacturerNameIsOnlyAvailableThroughTranslationFallback(): void
    {
        $product = $this->createProduct(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: $this->createManufacturer(name: null, translatedName: 'ACME')
        );

        $svg = $this->createResolver()->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED);

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
    }

    public function testResolvesToSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($product);

        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
        static::assertStringContainsString('ACME', $svg);
        static::assertStringContainsString('3', $svg);
    }

    public function testResolveDefaultsToFullLabelType(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($product);

        static::assertIsString($svg);
        static::assertStringNotContainsString('nested', $svg);
    }

    public function testResolvesNestedLabelToNullWhenGuaranteeNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToNullWhenManufacturerNumberMissing(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: null, guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToNullWhenDurationInvalid(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 12);

        static::assertNull($this->createResolver()->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($product, GaranLabelResolver::LABEL_TYPE_NESTED);

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
        static::assertStringContainsString('3', $svg);
    }

    private function createResolver(): GaranLabelResolver
    {
        $twig = static::createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context) => str_contains($template, 'nested-label')
                ? \sprintf('<svg>nested %s</svg>', $context['guarantee'])
                : \sprintf('<svg>%s %s %s</svg>', $context['manufacturer'], $context['productNumber'], $context['guarantee'])
        );

        return new GaranLabelResolver(new GaranLabelDurationFormatter(), new GaranLabelRenderer($twig));
    }

    /**
     * The DAL only fills `name` with the translation of the current language, while the resolved
     * translation chain (including the parent language fallback) ends up in `translated`.
     */
    private function createManufacturer(?string $name, ?string $translatedName): ProductManufacturerEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setName($name);
        $manufacturer->setTranslated(['name' => $translatedName]);

        return $manufacturer;
    }

    private function createProduct(bool $guaranteeConfirmed, ?string $manufacturerNumber, ?int $guaranteeMonths, ?ProductManufacturerEntity $manufacturer = null): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId('product-id');
        $product->setManufacturer($manufacturer ?? $this->createManufacturer(name: 'ACME', translatedName: 'ACME'));
        $product->setManufacturerNumber($manufacturerNumber);
        $product->setGuaranteeMonths($guaranteeMonths);
        $product->setGuaranteeConfirmed($guaranteeConfirmed);

        return $product;
    }
}
