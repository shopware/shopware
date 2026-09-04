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

        $factory = new RenderingSpecificationFactory();
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

        $factory = new RenderingSpecificationFactory();
        $specification = $factory->createWithoutLayout($source, 'abc123', $request, $context);

        static::assertSame([], $specification->dataRequirements);
        static::assertSame($placeholders, $specification->placeholderValues);
        static::assertSame($request, $specification->request);
        static::assertSame('element-42', $specification->targetElementId);
        static::assertSame([], $specification->cacheTags);
    }
}
