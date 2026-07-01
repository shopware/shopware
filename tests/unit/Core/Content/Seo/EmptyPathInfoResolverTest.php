<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\EmptyPathInfoResolver;
use Shopware\Core\Content\Seo\ResolvedSeoUrl;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(EmptyPathInfoResolver::class)]
class EmptyPathInfoResolverTest extends TestCase
{
    public function testResolveUrlReturnsRootForEmptyPath(): void
    {
        $decorated = $this->createMock(AbstractSeoResolver::class);
        $decorated->expects($this->never())->method('resolveUrl');

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), ''));

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveUrlReturnsRootForSlashOnlyPath(): void
    {
        $decorated = $this->createMock(AbstractSeoResolver::class);
        $decorated->expects($this->never())->method('resolveUrl');

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), '/'));

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveUrlDelegatesNonEmptyPathToDecorated(): void
    {
        $expected = new ResolvedSeoUrl(pathInfo: '/detail/1234', isCanonical: true);

        $decorated = $this->createMock(AbstractSeoResolver::class);
        $decorated
            ->expects($this->once())
            ->method('resolveUrl')
            ->with(static::callback(static fn (SeoUrlRequestContext $context): bool => $context->pathInfo === '/awesome-product'))
            ->willReturn($expected);

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), '/awesome-product'));

        static::assertSame($expected, $resolved);
    }

    public function testGetDecoratedReturnsInjectedResolver(): void
    {
        $decorated = $this->createMock(AbstractSeoResolver::class);
        $resolver = new EmptyPathInfoResolver($decorated);

        static::assertSame($decorated, $resolver->getDecorated());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedResolveReturnsRootArrayForEmptyPath(): void
    {
        $decorated = $this->createMock(AbstractSeoResolver::class);
        $decorated->expects($this->never())->method('resolveUrl');

        $resolver = new EmptyPathInfoResolver($decorated);

        $data = $resolver->resolve(Uuid::randomHex(), Uuid::randomHex(), '');

        static::assertSame(['pathInfo' => '/', 'isCanonical' => false], $data);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedResolveProjectsAllPopulatedFieldsToArray(): void
    {
        $decorated = $this->createMock(AbstractSeoResolver::class);
        $decorated
            ->expects($this->once())
            ->method('resolveUrl')
            ->willReturn(new ResolvedSeoUrl(
                pathInfo: '/detail/1234',
                isCanonical: true,
                id: 'binaryId',
                canonicalPathInfo: '/awesome-product',
                seoPathInfo: 'awesome-product',
            ));

        $resolver = new EmptyPathInfoResolver($decorated);

        $data = $resolver->resolve(Uuid::randomHex(), Uuid::randomHex(), '/awesome-product');

        static::assertSame([
            'pathInfo' => '/detail/1234',
            'isCanonical' => true,
            'id' => 'binaryId',
            'canonicalPathInfo' => '/awesome-product',
            'seoPathInfo' => 'awesome-product',
        ], $data);
    }

    public function testDeprecatedResolveThrowsWhenFeatureActive(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('Feature v6.8.0.0 must be active to assert the throw behaviour.');
        }

        $resolver = new EmptyPathInfoResolver($this->createMock(AbstractSeoResolver::class));

        $this->expectException(\Throwable::class);
        $resolver->resolve(Uuid::randomHex(), Uuid::randomHex(), '/awesome-product');
    }
}
