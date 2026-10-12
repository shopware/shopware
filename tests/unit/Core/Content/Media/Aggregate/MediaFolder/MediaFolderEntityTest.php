<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Aggregate\MediaFolder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFolderEntity::class)]
class MediaFolderEntityTest extends TestCase
{
    public function testGetMediaFallsBackToAnEmptyCollection(): void
    {
        $folder = new MediaFolderEntity();

        $media = $folder->getMedia();

        static::assertCount(0, $media);
        static::assertSame($media, $folder->getMedia());
    }

    public function testGetMediaReturnsTheAssignedCollection(): void
    {
        $folder = new MediaFolderEntity();
        $media = new MediaCollection();
        $folder->setMedia($media);

        static::assertSame($media, $folder->getMedia());
    }
}
