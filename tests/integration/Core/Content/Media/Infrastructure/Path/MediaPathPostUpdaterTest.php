<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopware\Core\Content\Media\Core\Application\MediaPathStorage;
use Shopware\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopware\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater
 */
class MediaPathPostUpdaterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testIterate(): void
    {
        $updater = new MediaPathPostUpdater(
            $this->getContainer()->get(IteratorFactory::class),
            $this->getContainer()->get(MediaPathUpdater::class),
            $this->getContainer()->get(Connection::class)
        );

        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class), 250);
        $queue->addInsert('media', ['id' => $ids->getBytes('media-1'), 'file_name' => 'test', 'file_extension' => 'png', 'created_at' => '2021-01-01 00:00:00']);
        $queue->addInsert('media', ['id' => $ids->getBytes('media-2'), 'file_name' => 'test', 'file_extension' => 'png', 'created_at' => '2021-01-01 00:00:00']);
        $queue->execute();

        $message = $updater->iterate(null);

        static::assertNotNull($message);
        static::assertNotEmpty($message->getData());
        static::assertNotNull($message->getOffset());
    }

    public function testHandle(): void
    {
        $internal = new MediaPathUpdater(
            new PlainPathStrategy(),
            $this->getContainer()->get(MediaLocationBuilder::class),
            $this->getContainer()->get(MediaPathStorage::class)
        );

        $updater = new MediaPathPostUpdater(
            $this->getContainer()->get(IteratorFactory::class),
            $internal,
            $this->getContainer()->get(Connection::class)
        );

        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class), 250);
        $queue->addInsert('media', ['id' => $ids->getBytes('media-1'), 'file_name' => 'media-1', 'file_extension' => 'png', 'created_at' => '2021-01-01 00:00:00']);
        $queue->addInsert('media', ['id' => $ids->getBytes('media-2'), 'file_name' => 'media-2', 'file_extension' => 'png', 'created_at' => '2021-01-01 00:00:00']);
        $queue->execute();

        $message = new EntityIndexingMessage([$ids->get('media-1'), $ids->get('media-2')]);

        $updater->handle($message);

        $paths = $this->getContainer()
            ->get(Connection::class)
            ->fetchFirstColumn(
                'SELECT path FROM media WHERE id IN (:ids)',
                ['ids' => $ids->getByteList(['media-1', 'media-2'])],
                ['ids' => ArrayParameterType::BINARY]
            );

        static::assertCount(2, $paths);
        static::assertContains('media/media-1.png', $paths);
        static::assertContains('media/media-2.png', $paths);
    }
}
