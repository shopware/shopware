<?php declare(strict_types=1);

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DefaultMediaResolver $mediaResolver;

    private FilesystemInterface $publicFilesystem;

    public function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->mediaResolver = new DefaultMediaResolver($this->publicFilesystem);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->publicFilesystem->put('/bundles/core/assets/default/cms/shopware.jpg', '');
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('core/assets/default/cms/shopware.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals('shopware', $media->getFileName());
        static::assertEquals('image/jpeg', $media->getMimeType());
        static::assertEquals('jpg', $media->getFileExtension());
    }
}
