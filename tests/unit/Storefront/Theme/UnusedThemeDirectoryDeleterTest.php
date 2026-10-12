<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UnusedThemeDirectoryDeleter::class)]
class UnusedThemeDirectoryDeleterTest extends TestCase
{
    private const NOW = '2026-09-29 12:00:00';

    private Filesystem $filesystem;

    private MockClock $clock;

    private UnusedThemeDirectoryDeleter $deleter;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->clock = new MockClock(self::NOW);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['salesChannelId' => 'salesChannelId', 'themeId' => 'themeId'],
        ]);

        $themePathBuilder = static::createStub(AbstractThemePathBuilder::class);
        $themePathBuilder->method('assemblePath')->willReturn('usedPrefix');

        $this->deleter = new UnusedThemeDirectoryDeleter(
            $connection,
            $this->filesystem,
            $themePathBuilder,
            $this->clock
        );
    }

    public function testUsedDirectoriesAreKept(): void
    {
        $this->filesystem->write('theme/usedPrefix/css/all.css', 'css');
        $this->filesystem->write('theme/themeId/assets/logo.png', 'png');

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertTrue($this->filesystem->fileExists('theme/usedPrefix/css/all.css'));
        static::assertTrue($this->filesystem->fileExists('theme/themeId/assets/logo.png'));
        static::assertFalse($this->filesystem->fileExists('theme/usedPrefix/.retired'));
    }

    public function testRetiredMarkerIsRemovedWhenDirectoryIsUsedAgain(): void
    {
        $this->filesystem->write('theme/usedPrefix/css/all.css', 'css');
        $this->filesystem->write('theme/usedPrefix/.retired', (string) $this->timestamp('-48 hours'));

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertTrue($this->filesystem->fileExists('theme/usedPrefix/css/all.css'));
        static::assertFalse($this->filesystem->fileExists('theme/usedPrefix/.retired'));
    }

    public function testUnusedDirectoryWithOldFilesIsMarkedAsRetiredInsteadOfBeingDeleted(): void
    {
        $this->filesystem->write('theme/oldPrefix/css/all.css', 'css', ['timestamp' => $this->timestamp('-25 hours')]);

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertTrue($this->filesystem->fileExists('theme/oldPrefix/css/all.css'));
        static::assertSame((string) $this->timestamp(), $this->filesystem->read('theme/oldPrefix/.retired'));
    }

    public function testDirectoryStillBeingWrittenIsNotMarked(): void
    {
        $this->filesystem->write('theme/inProgressPrefix/css/all.css', 'css', ['timestamp' => $this->timestamp('-25 hours')]);
        $this->filesystem->write('theme/inProgressPrefix/js/all.js', 'js', ['timestamp' => $this->timestamp('-1 minute')]);

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertFalse($this->filesystem->fileExists('theme/inProgressPrefix/.retired'));
    }

    public function testEmptyDirectoryIsMarkedAsRetired(): void
    {
        $this->filesystem->createDirectory('theme/emptyPrefix');

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertSame((string) $this->timestamp(), $this->filesystem->read('theme/emptyPrefix/.retired'));
    }

    public function testUnusedDirectoryIsKeptWithinGracePeriod(): void
    {
        $this->filesystem->write('theme/oldPrefix/css/all.css', 'css');
        $this->filesystem->write('theme/oldPrefix/.retired', (string) $this->timestamp('-23 hours'));

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertTrue($this->filesystem->fileExists('theme/oldPrefix/css/all.css'));
        static::assertSame((string) $this->timestamp('-23 hours'), $this->filesystem->read('theme/oldPrefix/.retired'));
    }

    public function testUnusedDirectoryIsDeletedAfterGracePeriod(): void
    {
        $this->filesystem->write('theme/oldPrefix/css/all.css', 'css');
        $this->filesystem->write('theme/oldPrefix/.retired', (string) $this->timestamp('-25 hours'));
        $this->filesystem->write('theme/olderPrefix/js/all.js', 'js');
        $this->filesystem->write('theme/olderPrefix/.retired', (string) $this->timestamp('-3 days'));

        static::assertSame(2, $this->deleter->deleteUnusedDirectories());

        static::assertFalse($this->filesystem->directoryExists('theme/oldPrefix'));
        static::assertFalse($this->filesystem->directoryExists('theme/olderPrefix'));
    }

    public function testUnreadableMarkerIsRewritten(): void
    {
        $this->filesystem->write('theme/oldPrefix/css/all.css', 'css', ['timestamp' => $this->timestamp('-25 hours')]);
        $this->filesystem->write('theme/oldPrefix/.retired', 'not-a-timestamp', ['timestamp' => $this->timestamp('-25 hours')]);

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());

        static::assertTrue($this->filesystem->fileExists('theme/oldPrefix/css/all.css'));
        static::assertSame((string) $this->timestamp(), $this->filesystem->read('theme/oldPrefix/.retired'));
    }

    public function testMarkedDirectoryIsDeletedOnceTheGracePeriodHasPassed(): void
    {
        $this->filesystem->write('theme/oldPrefix/css/all.css', 'css', ['timestamp' => $this->timestamp('-25 hours')]);

        static::assertSame(0, $this->deleter->deleteUnusedDirectories());
        static::assertTrue($this->filesystem->directoryExists('theme/oldPrefix'));

        $this->clock->modify('+24 hours');

        static::assertSame(1, $this->deleter->deleteUnusedDirectories());
        static::assertFalse($this->filesystem->directoryExists('theme/oldPrefix'));
    }

    private function timestamp(string $modifier = 'now'): int
    {
        return (new \DateTimeImmutable(self::NOW))->modify($modifier)->getTimestamp();
    }
}
