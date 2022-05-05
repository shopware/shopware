<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaFolderConfigurationThumbnailSize;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderConfigurationMediaThumbnailSizeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateConfiguration(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_folder_configuration.repository');

        $configurationId = Uuid::randomHex();
        $sizeId = Uuid::randomHex();

        $repository->create([
            [
                'id' => $configurationId,
                'createThumbnails' => true,
                'mediaThumbnailSizes' => [
                    [
                        'id' => $sizeId,
                        'width' => 100,
                        'height' => 100,
                    ],
                ],
            ],
        ], $context);

        $criteria = new Criteria([$configurationId]);
        $criteria->addAssociation('mediaThumbnailSizes');

        $read = $repository->search($criteria, $context);
        $configuration = $read->get($configurationId);

        static::assertNotNull($configuration);
        static::assertEquals(1, $configuration->getMediaThumbnailSizes()->count());
        static::assertNotNull($configuration->getMediaThumbnailSizes()->get($sizeId));
    }

    public function testCreateThumbnailSize(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_thumbnail_size.repository');

        $sizeId = Uuid::randomHex();
        $confId = Uuid::randomHex();

        $repository->upsert([
            [
                'id' => $sizeId,
                'width' => 100,
                'height' => 100,
                'mediaFolderConfigurations' => [
                    [
                        'id' => $confId,
                        'createThumbnails' => true,
                    ],
                ],
            ],
        ], $context);

        $criteria = (new Criteria())
            ->addAssociation('mediaFolderConfigurations');

        $search = $repository->search($criteria, $context);

        $size = $search->getEntities()->get($sizeId);
        static::assertNotNull($size);
        static::assertEquals(1, $size->getMediaFolderConfigurations()->count());
        static::assertNotNull($size->getMediaFolderConfigurations()->get($confId));
    }
}
