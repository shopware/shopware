<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\SpecificationData;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RenderingSpecificationResolver::class)]
class RenderingSpecificationResolverTest extends TestCase
{
    #[TestDox('returns specification from first supporting source')]
    public function testResolveReturnsSpecificationFromFirstSupportingSource(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/product/abc';

        $source1 = static::createStub(AbstractSpecificationSource::class);
        $source1->method('supports')->willReturn(true);
        $source1->method('resolveLayoutId')->willReturn('layout-1');
        $source1->method('resolveSpecificationData')->willReturn(new SpecificationData([], PlaceholderValues::from([])));
        $source1->method('resolveTargetElementId')->willReturn(null);
        $source1->method('resolveCacheTags')->willReturn([]);

        $source2 = static::createStub(AbstractSpecificationSource::class);

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source1, $source2], $factory);

        $result = $resolver->resolve($path, $request, $context);

        static::assertSame('layout-1', $result->layoutId);
        static::assertSame($request, $result->request);
    }

    #[TestDox('skips sources that do not support the path')]
    public function testResolveSkipsSourcesThatDoNotSupport(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/category/xyz';

        $source1 = static::createStub(AbstractSpecificationSource::class);
        $source1->method('supports')->willReturn(false);

        $source2 = static::createStub(AbstractSpecificationSource::class);
        $source2->method('supports')->willReturn(true);
        $source2->method('resolveLayoutId')->willReturn('layout-2');
        $source2->method('resolveSpecificationData')->willReturn(new SpecificationData([], PlaceholderValues::from([])));
        $source2->method('resolveTargetElementId')->willReturn(null);
        $source2->method('resolveCacheTags')->willReturn([]);

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source1, $source2], $factory);

        $result = $resolver->resolve($path, $request, $context);

        static::assertSame('layout-2', $result->layoutId);
    }

    #[TestDox('throws when no source supports the path')]
    public function testResolveThrowsNoFactoryCanHandleWhenNoSourceSupports(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $path = '/unknown/path';

        $source = static::createStub(AbstractSpecificationSource::class);
        $source->method('supports')->willReturn(false);

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source], $factory);

        $this->expectExceptionObject(ContentSystemException::noFactoryCanHandle($path));

        $resolver->resolve($path, $request, $context);
    }
}
