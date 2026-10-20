<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelTwigFilter::class)]
class GaranLabelTwigFilterTest extends TestCase
{
    public function testGetFiltersRegistersSwGaranLabelFilters(): void
    {
        $filter = $this->createFilter([]);

        $filters = $filter->getFilters();

        static::assertCount(7, $filters);
        static::assertSame('sw_garan_label_duration', $filters[0]->getName());
        static::assertSame('sw_garan_label', $filters[1]->getName());
        static::assertSame('sw_garan_label_nested', $filters[2]->getName());
        static::assertSame('sw_garan_label_data_uri', $filters[3]->getName());
        static::assertSame('sw_garan_label_nested_uri', $filters[4]->getName());
        static::assertSame('sw_garan_label_text_length', $filters[5]->getName());
        static::assertSame('sw_garan_label_duration_text_length', $filters[6]->getName());
    }

    public function testTextLengthFiltersDelegateToFitter(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->fitTextLength('Acme', 190.43, 9));
        static::assertSame(188.93, $filter->fitTextLength('Shopware Lebensmittel und Nahrungsmittel GmbH', 190.43, 9));

        static::assertNull($filter->fitDurationTextLength('25', 116.4, 80, -0.03));
        static::assertSame(114.9, $filter->fitDurationTextLength('25,5', 116.4, 80, -0.03));
    }

    public function testFormatDurationDelegatesToFormatter(): void
    {
        $filter = $this->createFilter([]);

        static::assertSame('3', $filter->formatDuration(36));
        static::assertNull($filter->formatDuration(12));
        static::assertNull($filter->formatDuration(null));
    }

    public function testRenderReturnsNullForNullProductId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->render(null, Context::createDefaultContext()));
    }

    public function testRenderReturnsNullWhenProductNotFound(): void
    {
        $filter = $this->createFilter([new ProductCollection()]);

        static::assertNull($filter->render('product-id', Context::createDefaultContext()));
    }

    public function testRenderReturnsNullWhenProductNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        static::assertNull($filter->render($product->getId(), Context::createDefaultContext()));
    }

    public function testRenderReturnsSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        $svg = $filter->render($product->getId(), Context::createDefaultContext());

        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
    }

    public function testRenderNestedLabelReturnsNullForNullProductId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderNestedLabel(null, Context::createDefaultContext()));
    }

    public function testRenderNestedLabelReturnsNullWhenProductNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        static::assertNull($filter->renderNestedLabel($product->getId(), Context::createDefaultContext()));
    }

    public function testRenderNestedLabelReturnsSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        $svg = $filter->renderNestedLabel($product->getId(), Context::createDefaultContext());

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
        static::assertStringContainsString('3', $svg);
    }

    public function testRenderAsDataUriReturnsNullForNullProductId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderAsDataUri(null, Context::createDefaultContext()));
    }

    public function testRenderAsDataUriReturnsNullWhenProductNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        static::assertNull($filter->renderAsDataUri($product->getId(), Context::createDefaultContext()));
    }

    public function testRenderAsDataUriReturnsBase64EncodedSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        $dataUri = $filter->renderAsDataUri($product->getId(), Context::createDefaultContext());

        static::assertIsString($dataUri);
        static::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, \strlen('data:image/svg+xml;base64,')), true);
        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
    }

    public function testRenderNestedAsDataUriReturnsNullForNullProductId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderNestedAsDataUri(null, Context::createDefaultContext()));
    }

    public function testRenderNestedAsDataUriReturnsNullWhenProductNotConfirmed(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: false);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        static::assertNull($filter->renderNestedAsDataUri($product->getId(), Context::createDefaultContext()));
    }

    public function testRenderNestedAsDataUriReturnsBase64EncodedSvgForCompleteConfirmedProduct(): void
    {
        $product = $this->createProduct(guaranteeConfirmed: true);

        $filter = $this->createFilter([new ProductCollection([$product])]);

        $dataUri = $filter->renderNestedAsDataUri($product->getId(), Context::createDefaultContext());

        static::assertIsString($dataUri);
        static::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, \strlen('data:image/svg+xml;base64,')), true);
        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createFilter(array $searchResults): GaranLabelTwigFilter
    {
        $twig = static::createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context) => str_contains($template, 'nested-label')
                ? \sprintf('<svg>nested %s</svg>', $context['guarantee'])
                : \sprintf('<svg>%s %s %s</svg>', $context['manufacturer'], $context['productNumber'], $context['guarantee'])
        );

        $resolver = new GaranLabelResolver(new GaranLabelDurationFormatter(), new GaranLabelRenderer($twig));

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository($searchResults, new ProductDefinition());

        return new GaranLabelTwigFilter(new GaranLabelDurationFormatter(), $productRepository, $resolver);
    }

    private function createProduct(bool $guaranteeConfirmed): ProductEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setName('ACME');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $product = new ProductEntity();
        $product->setId('product-id');
        $product->setManufacturer($manufacturer);
        $product->setManufacturerNumber('ACME-123');
        $product->setGuaranteeMonths(36);
        $product->setGuaranteeConfirmed($guaranteeConfirmed);

        return $product;
    }
}
