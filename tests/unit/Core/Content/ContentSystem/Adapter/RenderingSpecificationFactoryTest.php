<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Content\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\SpecificationData;
use Shopware\Core\Test\Generator;
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

        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('resolveLayoutId')->willReturn('layout-1');
        $source->method('resolveSpecificationData')->willReturn($specData);
        $source->method('resolveTargetElementId')->willReturn('element-42');
        $source->method('resolveCacheTags')->willReturn(['product-abc123']);

        $factory = new RenderingSpecificationFactory();
        $result = $factory->create($source, $path, $request, $context);

        static::assertSame('layout-1', $result->layoutId);
        static::assertSame([], $result->dataRequirements);
        static::assertSame($placeholders, $result->placeholderValues);
        static::assertSame($request, $result->request);
        static::assertSame('element-42', $result->targetElementId);
        static::assertSame(['product-abc123'], $result->cacheTags);
    }

    #[TestDox('passes through null target element id from source')]
    public function testCreatePassesThroughNullTargetElementId(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/product/abc123';
        $placeholders = PlaceholderValues::from(['productId' => 'abc123']);
        $specData = new SpecificationData([], $placeholders);

        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('resolveLayoutId')->willReturn('layout-1');
        $source->method('resolveSpecificationData')->willReturn($specData);
        $source->method('resolveTargetElementId')->willReturn(null);
        $source->method('resolveCacheTags')->willReturn([]);

        $factory = new RenderingSpecificationFactory();
        $result = $factory->create($source, $path, $request, $context);

        static::assertNull($result->targetElementId);
    }
}
