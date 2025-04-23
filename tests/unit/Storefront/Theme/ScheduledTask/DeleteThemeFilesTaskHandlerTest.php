<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\ScheduledTask;

use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesTaskHandler::class)]
class DeleteThemeFilesTaskHandlerTest extends TestCase
{
    public function testRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            ['salesChannelId' => 'salesChannelId1', 'themeId' => 'themeId1'],
        ]);

        $themeFileSystem = $this->createMock(FilesystemOperator::class);
        $themeFileSystem->expects($this->exactly(4))->method('listContents')
            ->willReturnMap([
                [
                    'theme',
                    FilesystemReader::LIST_SHALLOW,
                    new DirectoryListing([
                        new DirectoryAttributes('theme/usedThemePath'),
                        new DirectoryAttributes('theme/unusedThemePathWithoutFiles'),
                        new DirectoryAttributes('theme/unusedThemePathOlderThanOneDay'),
                        new DirectoryAttributes('theme/unusedThemePathNewerThanOneDay'),
                    ]),
                ],
                [
                    'theme/unusedThemePathWithoutFiles',
                    FilesystemReader::LIST_DEEP,
                    new DirectoryListing([
                        new DirectoryAttributes('theme/unusedThemePathWithoutFiles/foo'),
                    ]),
                ],
                [
                    'theme/unusedThemePathOlderThanOneDay',
                    FilesystemReader::LIST_DEEP,
                    new DirectoryListing([
                        new DirectoryAttributes('theme/unusedThemePathOlderThanOneDay/foo'),
                        new FileAttributes('theme/unusedThemePathOlderThanOneDay/fileWithoutTimestamp.txt'),
                        new FileAttributes(
                            'theme/unusedThemePathOlderThanOneDay/file1.txt',
                            lastModified: (new \DateTimeImmutable())->modify('-25 hours')->getTimestamp()
                        ),
                    ]),
                ],
                [
                    'theme/unusedThemePathNewerThanOneDay',
                    FilesystemReader::LIST_DEEP,
                    new DirectoryListing([
                        new FileAttributes(
                            'theme/unusedThemePathNewerThanOneDay/file2.txt',
                            lastModified: (new \DateTimeImmutable())->modify('-23 hours')->getTimestamp()
                        ),
                    ]),
                ],
            ]);
        $themeFileSystem->expects($this->exactly(2))->method('deleteDirectory')->willReturnMap([
            ['theme/unusedThemePathWithoutFiles'],
            ['theme/unusedThemePathOlderThanOneDay'],
        ]);

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->exactly(2))->method('invalidate')->willReturnMap([
            [['theme_scripts_theme/unusedThemePathWithoutFiles']],
            [['theme_scripts_theme/unusedThemePathOlderThanOneDay']],
        ]);

        $themePathBuilder = $this->createMock(AbstractThemePathBuilder::class);
        $themePathBuilder->expects($this->once())->method('assemblePath')->with('salesChannelId1', 'themeId1')->willReturn('usedThemePath');

        $handler = new DeleteThemeFilesTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $connection,
            $themeFileSystem,
            $cacheInvalidator,
            $themePathBuilder
        );

        $handler->run();
    }
}
