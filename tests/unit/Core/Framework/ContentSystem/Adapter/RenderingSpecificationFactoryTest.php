<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\StaticSpecificationSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderingSpecificationFactory::class)]
class RenderingSpecificationFactoryTest extends TestCase
{
    #[TestDox('pairs the resolved layout id with a specification assembled from all source methods')]
    public function testCreateAssemblesSpecificationFromAllSourceMethods(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/product/abc123';
        $placeholders = PlaceholderValues::from(['productId' => 'abc123']);
        $specData = new SpecificationData([], $placeholders);

        $source = new StaticSpecificationSource(
            layoutId: 'layout-1',
            specificationData: $specData,
            targetElementId: 'element-42',
            cacheTags: ['product-abc123'],
        );

        $factory = new RenderingSpecificationFactory([]);
        $result = $factory->create($source, $path, $request, $context);

        static::assertSame('layout-1', $result->layoutId);
        static::assertSame([], $result->specification->dataRequirements);
        static::assertSame($placeholders, $result->specification->placeholderValues);
        static::assertSame($request, $result->specification->request);
        static::assertSame('element-42', $result->specification->targetElementId);
        static::assertSame(['product-abc123'], $result->specification->cacheTags);

        $defaultsSource = new StaticSpecificationSource(
            layoutId: 'layout-2',
            specificationData: new SpecificationData([], PlaceholderValues::from([])),
        );

        $defaultsResult = $factory->create($defaultsSource, $path, $request, $context);

        static::assertSame('layout-2', $defaultsResult->layoutId);
        static::assertNull($defaultsResult->specification->targetElementId);
        static::assertSame([], $defaultsResult->specification->cacheTags);
    }

    #[TestDox('assembles a bare specification without resolving a layout id or cache tags')]
    public function testCreateWithoutLayoutAssemblesBareSpecification(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $placeholders = PlaceholderValues::from(['productId' => 'abc123']);
        $specData = new SpecificationData([], $placeholders);

        $source = new StaticSpecificationSource(
            specificationData: $specData,
            targetElementId: 'element-42',
            cacheTags: ['product-abc123'],
            failOnResolveLayoutId: true,
        );

        $factory = new RenderingSpecificationFactory([]);
        $specification = $factory->createWithoutLayout($source, 'abc123', $request, $context);

        static::assertSame([], $specification->dataRequirements);
        static::assertSame($placeholders, $specification->placeholderValues);
        static::assertSame($request, $specification->request);
        static::assertSame('element-42', $specification->targetElementId);
        static::assertSame([], $specification->cacheTags);
    }

    #[TestDox('adds the scalar query parameters the source declares to the placeholder values the source derived, on a route and on a preview')]
    public function testAddsDeclaredScalarQueryParameters(): void
    {
        $request = new Request(['referrerCategoryId' => 'category-1', 'tags' => ['a'], 'color' => 'red', '0' => 'x']);
        $context = Generator::generateSalesChannelContext();
        $source = new StaticSpecificationSource(
            specificationData: new SpecificationData([], PlaceholderValues::from(['productId' => 'abc123'])),
            supportedEntityType: 'product',
            placeholderQueryParameters: ['referrerCategoryId', 'tags'],
        );

        $factory = new RenderingSpecificationFactory([]);
        $expected = ['referrerCategoryId' => 'category-1', 'productId' => 'abc123'];

        static::assertSame($expected, $factory->create($source, '/product/abc123', $request, $context)->specification->placeholderValues->all());
        static::assertSame($expected, $factory->createWithoutLayout($source, 'abc123', $request, $context)->placeholderValues->all());
    }

    #[TestDox('keeps a placeholder value the source derived when a declared query parameter has its name')]
    public function testQueryParameterCannotReplaceADerivedPlaceholder(): void
    {
        $source = new StaticSpecificationSource(
            specificationData: new SpecificationData([], PlaceholderValues::from(['productId' => 'abc123'])),
            placeholderQueryParameters: ['productId'],
        );

        $result = (new RenderingSpecificationFactory([]))->create($source, '/product/abc123', new Request(['productId' => 'other']), Generator::generateSalesChannelContext());

        static::assertSame(['productId' => 'abc123'], $result->specification->placeholderValues->all());
    }

    #[TestDox('never turns a query parameter excluded from the HTTP cache key into a placeholder value, and keeps it on the request')]
    public function testCacheIgnoredQueryParametersCannotBecomePlaceholders(): void
    {
        $parameters = [
            'utm_source' => '<a href="https://example.test/phishing">Campaign</a>',
            'custom_tracking' => 'injected content',
            'campaign' => 'summer',
            'page' => 2,
        ];
        $request = new Request($parameters);
        $source = new StaticSpecificationSource(
            specificationData: new SpecificationData([], PlaceholderValues::from(['landingPageId' => 'landing-page-id'])),
            placeholderQueryParameters: ['utm_source', 'custom_tracking', 'campaign', 'page'],
        );

        $result = (new RenderingSpecificationFactory(['utm_source', 'custom_tracking']))->create($source, '/landing-page/landing-page-id', $request, Generator::generateSalesChannelContext());

        static::assertSame(['campaign' => 'summer', 'page' => 2, 'landingPageId' => 'landing-page-id'], $result->specification->placeholderValues->all());
        static::assertSame($parameters, $request->query->all());
    }
}
