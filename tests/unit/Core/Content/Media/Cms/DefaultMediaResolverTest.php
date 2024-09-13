<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(DefaultMediaResolver::class)]
class DefaultMediaResolverTest extends TestCase
{
    private FilesystemOperator&MockObject $filesystem;

    private DefaultMediaResolver $mediaResolver;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(FilesystemOperator::class);
        $this->mediaResolver = new DefaultMediaResolver($this->filesystem);
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->mediaResolver->getDecorated();
    }

    public function testGetDefaultCmsMediaEntityNoFile(): void
    {
        $this->filesystem->method('fileExists')
            ->with('bundles/storefront/assets/default/cms/nonexistent.jpg')
            ->willReturn(false);

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/nonexistent.jpg');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityMimeTypeIsMissing(): void
    {
        $this->filesystem->method('fileExists')
            ->with('bundles/storefront/assets/default/cms/shopware.jpg')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->with('bundles/storefront/assets/default/cms/shopware.jpg')
            ->willReturn('');

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/shopware.jpg');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityMissingExtension(): void
    {
        $this->filesystem->method('fileExists')
            ->with('bundles/storefront/assets/default/cms/shopware')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->with('bundles/storefront/assets/default/cms/shopware')
            ->willReturn('image/jpeg');

        $this->filesystem->method('mimeType')
            ->willReturnCallback(function ($filePath) {
                return $filePath === 'bundles/storefront/assets/default/cms/shopware' ? 'image/jpeg' : null;
            });

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/shopware');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityValidFile(): void
    {
        $this->filesystem->method('fileExists')
            ->with('bundles/storefront/assets/default/cms/shopware.jpg')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->with('bundles/storefront/assets/default/cms/shopware.jpg')
            ->willReturn('image/jpeg');

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/shopware.jpg');

        static::assertInstanceOf(MediaEntity::class, $result);
        static::assertEquals('shopware', $result->getFileName());
        static::assertEquals('image/jpeg', $result->getMimeType());
        static::assertEquals('jpg', $result->getFileExtension());
    }
}
