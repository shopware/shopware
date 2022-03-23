<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;

class DefaultMediaResolverTest extends TestCase
{
    private const FIXTURES_DIRECTORY = '/../fixtures';

    private DefaultMediaResolver $mediaResolver;

    public function setUp(): void
    {
        $this->mediaResolver = new DefaultMediaResolver(__DIR__ . self::FIXTURES_DIRECTORY);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('core/assets/default/cms/shopware.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals('shopware', $media->getFileName());
        static::assertEquals('image/jpeg', $media->getMimeType());
        static::assertEquals('jpg', $media->getFileExtension());
    }
}
