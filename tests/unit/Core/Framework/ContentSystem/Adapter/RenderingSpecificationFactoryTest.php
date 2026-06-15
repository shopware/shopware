<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\StaticSpecificationSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RenderingSpecificationFactory::class)]
class RenderingSpecificationFactoryTest extends TestCase
{
    #[TestDox('assembles specification from all source methods including cache tags')]
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
        static::assertSame([], $result->dataRequirements);
        static::assertSame($placeholders, $result->placeholderValues);
        static::assertSame($request, $result->request);
        static::assertSame('element-42', $result->targetElementId);
        static::assertSame(['product-abc123'], $result->cacheTags);
    }

    #[TestDox('assembles specification with null target element and empty cache tags')]
    public function testCreateAssemblesSpecificationWithNullTargetAndEmptyCacheTags(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/category/xyz';
        $specData = new SpecificationData([], PlaceholderValues::from([]));

        $source = new StaticSpecificationSource(
            layoutId: 'layout-2',
            specificationData: $specData,
        );

        $factory = new RenderingSpecificationFactory();
        $result = $factory->create($source, $path, $request, $context);

        static::assertSame('layout-2', $result->layoutId);
        static::assertNull($result->targetElementId);
        static::assertSame([], $result->cacheTags);
    }
}
