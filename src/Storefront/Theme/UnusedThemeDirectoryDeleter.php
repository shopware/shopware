<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Deletes theme directories that are no longer referenced by any sales channel/theme mapping.
 *
 * An unused directory is first marked as retired and only deleted once the grace period has
 * passed since that marking. Cached responses may still reference the directory of the
 * previously active theme, so its files have to stay available for a while after the switch.
 * The age of the compiled files themselves says nothing about when the switch happened.
 *
 * @internal
 */
#[Package('discovery')]
class UnusedThemeDirectoryDeleter
{
    public const RETIRED_MARKER_FILE = '.retired';

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

        $now = $this->clock->now()->getTimestamp();
        $graceBoundary = $this->clock->now()
            ->modify(\sprintf('-%d hours', self::GRACE_PERIOD_HOURS))
            ->getTimestamp();

        $deletedCount = 0;
        foreach ($this->themeFileSystem->listContents('theme') as $themeDirectory) {
            if (!$themeDirectory->isDir()) {
                continue;
            }

            $themePath = $themeDirectory->path();
            $markerPath = $themePath . \DIRECTORY_SEPARATOR . self::RETIRED_MARKER_FILE;

            if (\in_array($themePath, $usedThemePaths, true)) {
                // A theme can become active again, e.g. via `theme:change --no-compile`
                if ($this->themeFileSystem->fileExists($markerPath)) {
                    $this->themeFileSystem->delete($markerPath);
                }

                continue;
            }

            $retiredAt = $this->getRetiredAt($markerPath);
            if ($retiredAt === null) {
                $this->themeFileSystem->write($markerPath, (string) $now);

                continue;
            }

            if ($retiredAt > $graceBoundary) {
                continue;
            }

            $this->themeFileSystem->deleteDirectory($themePath);
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

    private function getRetiredAt(string $markerPath): ?int
    {
        if (!$this->themeFileSystem->fileExists($markerPath)) {
            return null;
        }

        $content = trim($this->themeFileSystem->read($markerPath));

        return ctype_digit($content) ? (int) $content : null;
    }
}
