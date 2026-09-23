<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFolderConfigurationEntity::class)]
class MediaFolderConfigurationEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $folders = new MediaFolderCollection();
        $sizes = new MediaThumbnailSizeCollection();

        $configuration = new MediaFolderConfigurationEntity();
        $configuration->setMediaFolders($folders);
        $configuration->setCreateThumbnails(true);
        $configuration->setKeepAspectRatio(false);
        $configuration->setMediaThumbnailSizes($sizes);
        $configuration->setThumbnailQuality(80);
        $configuration->setPrivate(true);
        $configuration->setNoAssociation(false);

        static::assertSame($folders, $configuration->getMediaFolders());
        static::assertTrue($configuration->getCreateThumbnails());
        static::assertFalse($configuration->getKeepAspectRatio());
        static::assertSame($sizes, $configuration->getMediaThumbnailSizes());
        static::assertSame(80, $configuration->getThumbnailQuality());
        static::assertTrue($configuration->isPrivate());
        static::assertFalse($configuration->isNoAssociation());
    }

    public function testThumbnailSizesRoAreReadableOutsideTwig(): void
    {
        $configuration = $this->configurationWithInternalSizes();
        $configuration->setMediaThumbnailSizesRo('serialized');

        static::assertSame('serialized', $configuration->getMediaThumbnailSizesRo());
    }

    public function testThumbnailSizesRoAreGuardedInsideTwig(): void
    {
        $configuration = $this->configurationWithInternalSizes();
        $configuration->setMediaThumbnailSizesRo('serialized');

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('mediaThumbnailSizesRo', MediaFolderConfigurationEntity::class));
        $configuration->getMediaThumbnailSizesRo();
    }

    private function configurationWithInternalSizes(): MediaFolderConfigurationEntity
    {
        $configuration = new MediaFolderConfigurationEntity();
        $configuration->internalSetEntityData(MediaFolderConfigurationDefinition::ENTITY_NAME, new FieldVisibility(['mediaThumbnailSizesRo']));

        return $configuration;
    }
}
