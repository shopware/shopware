<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Cleanup\CleanupProductDocumentArchivesTaskHandler;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupProductDocumentArchivesTaskHandler::class)]
class CleanupProductDocumentArchivesTaskHandlerTest extends TestCase
{
    private LocalFilesystem $localFilesystem;

    private string $root;

    protected function setUp(): void
    {
        $this->localFilesystem = new LocalFilesystem();
        $this->root = sys_get_temp_dir() . '/archive-cleanup-' . Uuid::randomHex();
        $this->localFilesystem->mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->localFilesystem->remove($this->root);
    }

    public function testDeletesStoredArchivesOlderThanADay(): void
    {
        $now = new \DateTimeImmutable('2026-11-10 12:00:00');
        $archives = $this->root . '/' . ProductDocumentArchiver::DIRECTORY;
        $old = $archives . '/drill/old.zip';
        $leftover = $archives . '/drill/old.zip.' . Uuid::randomHex();
        $recent = $archives . '/hammer/recent.zip';
        $other = $this->root . '/other/old.zip';
        $this->createFile($old, $now->modify('-25 hours'));
        $this->createFile($leftover, $now->modify('-2 days'));
        $this->createFile($recent, $now->modify('-23 hours'));
        $this->createFile($other, $now->modify('-30 days'));
        // the listing contains the folders too, and an old folder must not be deleted like a file
        $this->localFilesystem->touch($archives . '/drill', $now->modify('-2 days')->getTimestamp());

        $handler = new CleanupProductDocumentArchivesTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class),
            static::createStub(LoggerInterface::class),
            new Filesystem(new LocalFilesystemAdapter($this->root)),
            new MockClock($now),
        );
        $handler->run();

        static::assertFileDoesNotExist($old);
        static::assertFileDoesNotExist($leftover);
        static::assertFileExists($recent);
        static::assertFileExists($other);
    }

    private function createFile(string $path, \DateTimeImmutable $modified): void
    {
        $this->localFilesystem->dumpFile($path, 'zip');
        $this->localFilesystem->touch($path, $modified->getTimestamp());
    }
}
