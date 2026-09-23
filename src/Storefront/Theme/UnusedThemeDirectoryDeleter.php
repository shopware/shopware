<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use League\Flysystem\StorageAttributes;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Deletes theme directories that are no longer referenced by any sales channel/theme mapping.
 *
 * A directory is only removed when its files have not been modified within the grace period,
 * so that recently compiled themes still referenced by cached responses are kept long enough
 * to be served.
 *
 * @internal
 */
#[Package('discovery')]
class UnusedThemeDirectoryDeleter
{
    private const GRACE_PERIOD_HOURS = 24;

    public function __construct(
        private readonly Connection $connection,
        private readonly FilesystemOperator $themeFileSystem,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return int the number of deleted theme directories
     */
    public function deleteUnusedDirectories(): int
    {
        $usedThemePaths = $this->getUsedThemePaths();

        $themeDirectories = $this->themeFileSystem->listContents('theme')->filter(function (StorageAttributes $themeDirectory) use ($usedThemePaths) {
            if (\in_array($themeDirectory->path(), $usedThemePaths, true)) {
                return false;
            }

            $modifiedTimestampOfFirstFile = $this->getModifiedTimestampOfFirstFile($themeDirectory);

            if ($modifiedTimestampOfFirstFile === null) {
                return true;
            }

            $graceBoundary = $this->clock->now()
                ->modify(\sprintf('-%d hours', self::GRACE_PERIOD_HOURS))
                ->getTimestamp();

            return $graceBoundary > $modifiedTimestampOfFirstFile;
        });

        $deletedCount = 0;
        foreach ($themeDirectories as $themeDirectory) {
            $this->themeFileSystem->deleteDirectory($themeDirectory->path());
            ++$deletedCount;
        }

        return $deletedCount;
    }

    /**
     * @return list<string>
     */
    private function getUsedThemePaths(): array
    {
        $salesChannelThemeMappings = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sales_channel_id)) AS salesChannelId, LOWER(HEX(theme_id)) AS themeId
             FROM theme_sales_channel'
        );

        $themePaths = [];
        foreach (array_unique(array_column($salesChannelThemeMappings, 'themeId')) as $themeId) {
            $themePaths[] = 'theme' . \DIRECTORY_SEPARATOR . $themeId;
        }

        foreach ($salesChannelThemeMappings as $salesChannelThemeMapping) {
            $themePaths[] = 'theme' . \DIRECTORY_SEPARATOR . $this->themePathBuilder->assemblePath(
                $salesChannelThemeMapping['salesChannelId'],
                $salesChannelThemeMapping['themeId']
            );
        }

        return $themePaths;
    }

    private function getModifiedTimestampOfFirstFile(StorageAttributes $themeDirectory): ?int
    {
        foreach ($this->themeFileSystem->listContents($themeDirectory->path(), FilesystemReader::LIST_DEEP) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $lastModified = $file->lastModified();
            if ($lastModified === null) {
                continue;
            }

            return $lastModified;
        }

        return null;
    }
}
