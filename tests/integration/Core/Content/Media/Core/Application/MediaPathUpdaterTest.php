<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Core\Application;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopware\Core\Content\Media\Core\Application\MediaPathStorage;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Content\Media\Core\Strategy\FilenamePathStrategy;
use Shopware\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(MediaPathUpdater::class)]
class MediaPathUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUpdateMedia(): void
    {
        $ids = new IdsCollection();

        $inserts = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $inserts->addInsert('media', [
            'id' => $ids->getBytes('media-1'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $inserts->execute();

        $updater = new MediaPathUpdater(
            new FilenamePathStrategy(),
            $this->getContainer()->get(MediaLocationBuilder::class),
            $this->getContainer()->get(MediaPathStorage::class)
        );

        $updater->updateMedia($ids->getList(['media-1']));

        $paths = $this->getContainer()->get(Connection::class)
            ->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), path FROM media WHERE id IN (:ids)',
                ['ids' => $ids->getByteList(['media-1'])],
                ['ids' => ArrayParameterType::BINARY]
            );

        static::assertCount(1, $paths);
        static::assertArrayHasKey($ids->get('media-1'), $paths);
        static::assertEquals('media/90/e6/f2/test-file-1.png', $paths[$ids->get('media-1')]);
    }

    public function testUpdateThumbnail(): void
    {
        $ids = new IdsCollection();

        $inserts = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $inserts->addInsert('media', [
            'id' => $ids->getBytes('media-1'),
            'file_name' => 'test-file-1',
            'file_extension' => 'png',
            'created_at' => '2022-01-01',
        ]);

        $inserts->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('thumbnail-1'),
            'media_id' => $ids->getBytes('media-1'),
            'width' => 100,
            'height' => 100,
            'created_at' => '2022-01-01',
        ]);
        $inserts->addInsert('media_thumbnail', [
            'id' => $ids->getBytes('thumbnail-2'),
            'media_id' => $ids->getBytes('media-1'),
            'width' => 240,
            'height' => 240,
            'created_at' => '2022-01-01',
        ]);

        $inserts->execute();

        $updater = new MediaPathUpdater(
            new PlainPathStrategy(),
            $this->getContainer()->get(MediaLocationBuilder::class),
            $this->getContainer()->get(MediaPathStorage::class)
        );

        $updater->updateThumbnails($ids->getList(['thumbnail-1', 'thumbnail-2']));

        $paths = $this->getContainer()->get(Connection::class)
            ->fetchAllKeyValue(
                'SELECT LOWER(HEX(id)), path FROM media_thumbnail WHERE id IN (:ids)',
                ['ids' => $ids->getByteList(['thumbnail-1', 'thumbnail-2'])],
                ['ids' => ArrayParameterType::BINARY]
            );

        static::assertCount(2, $paths);
        static::assertArrayHasKey($ids->get('thumbnail-1'), $paths);
        static::assertEquals('thumbnail/test-file-1_100x100.png', $paths[$ids->get('thumbnail-1')]);

        static::assertArrayHasKey($ids->get('thumbnail-2'), $paths);
        static::assertEquals('thumbnail/test-file-1_240x240.png', $paths[$ids->get('thumbnail-2')]);
    }
}
