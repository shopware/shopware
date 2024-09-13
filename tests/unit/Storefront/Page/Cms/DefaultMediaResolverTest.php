<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Cms\DefaultMediaResolver;
use Symfony\Component\Asset\Package as SymfonyPackage;
use Symfony\Component\Asset\Packages;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(DefaultMediaResolver::class)]
class DefaultMediaResolverTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $decorated = $this->createMock(AbstractDefaultMediaResolver::class);
        $translator = $this->createMock(AbstractTranslator::class);
        $packages = new Packages();

        $resolver = new DefaultMediaResolver($decorated, $translator, $packages);
        static::assertSame($decorated, $resolver->getDecorated());
    }

    public function testGetDefaultCmsMediaEntity(): void
    {
        $decorated = $this->createMock(AbstractDefaultMediaResolver::class);
        $decorated->expects(static::once())
            ->method('getDefaultCmsMediaEntity')
            ->willReturn(new MediaEntity());

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects(static::exactly(2))
            ->method('trans')
            ->willReturn('media-title');

        $package = $this->createMock(SymfonyPackage::class);
        $package->method('getUrl')->willReturn('http://localhost');

        $packages = new Packages(null, ['asset' => $package]);

        $resolver = new DefaultMediaResolver($decorated, $translator, $packages);
        $media = $resolver->getDefaultCmsMediaEntity('media/path/');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals([
            'title' => 'media-title',
            'alt' => 'media-title',
        ], $media->getTranslated());
        static::assertSame('http://localhost', $media->getUrl());
    }

    public function testGetDefaultCmsMediaEntityReturnsNullIfNoMediaFound(): void
    {
        $decorated = $this->createMock(AbstractDefaultMediaResolver::class);
        $decorated->expects(static::once())
            ->method('getDefaultCmsMediaEntity')
            ->willReturn(null);

        $translator = $this->createMock(AbstractTranslator::class);
        $packages = new Packages();

        $resolver = new DefaultMediaResolver($decorated, $translator, $packages);
        $media = $resolver->getDefaultCmsMediaEntity('media/path/');

        static::assertNull($media);
    }
}
