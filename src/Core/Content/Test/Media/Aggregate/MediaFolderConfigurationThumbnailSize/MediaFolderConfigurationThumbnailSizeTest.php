<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media\Aggregate\MediaFolderConfigurationThumbnailSize;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaFolderConfigurationThumbnailSizeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateConfiguration()
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_folder_configuration.repository');

        $configurationId = Uuid::uuid4()->getHex();
        $sizeId = Uuid::uuid4()->getHex();

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

        $read = $repository->read(new ReadCriteria([$configurationId]), $context);
        $configuration = $read->get($configurationId);

        static::assertNotNull($configuration);
        static::assertEquals(1, $configuration->getMediaThumbnailSizes()->count());
        static::assertNotNull($configuration->getMediaThumbnailSizes()->get($sizeId));
    }

    public function testCreateThumbnailSize()
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_thumbnail_size.repository');

        $sizeId = Uuid::uuid4()->getHex();
        $confId = Uuid::uuid4()->getHex();

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

        $search = $repository->search((new Criteria())->addAssociation('mediaFolderConfigurations'), $context);

        $size = $search->getEntities()->get($sizeId);
        static::assertNotNull($size);
        static::assertEquals(1, $size->getMediaFolderConfigurations()->count());
        static::assertNotNull($size->getMediaFolderConfigurations()->get($confId));
    }
}
