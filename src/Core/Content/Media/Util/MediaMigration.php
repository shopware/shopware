<?php

declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Content\Media\Util;

use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Shopware\Content\Media\Event\MigrateAdvanceEvent;
use Shopware\Content\Media\Event\MigrateFinishEvent;
use Shopware\Content\Media\Event\MigrateStartEvent;
use Shopware\Content\Media\Util\MediaMigrationInterface;
use Shopware\Content\Media\Util\Strategy\StrategyInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaMigration implements MediaMigrationInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var StrategyInterface
     */
    private $toStrategy;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var EventDispatcherInterface
     */
    private $event;

    /**
     * @var array
     */
    private $statistics = [
        'migrated' => 0,
        'skipped' => 0,
    ];

    public function __construct(FilesystemInterface $filesystem, StrategyInterface $toStrategy, EventDispatcherInterface $event, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->toStrategy = $toStrategy;
        $this->logger = $logger;
        $this->event = $event;
    }

    public function run(bool $skipScan = false): void
    {
        $this->logger->notice('Migrating all media files in your filesystem. This might take some time, depending on the number of media files you have.');

        $fileCount = 0;
        if (!$skipScan) {
            $this->logger->debug('Scanning filesystem to count files...');
            $fileCount = $this->countFiles();
            $this->logger->debug(sprintf('Found %d files.', $fileCount));
        }

        $this->event->dispatch(MigrateStartEvent::EVENT_NAME, new MigrateStartEvent($fileCount));
        $this->logger->info('Starting migration.', ['fileCount' => $fileCount]);
        $this->migrateFiles();
        $this->logger->info('Finished migration.');
        $this->event->dispatch(MigrateFinishEvent::EVENT_NAME, new MigrateFinishEvent($this->statistics['migrated'], $this->statistics['skipped']));
    }

    public function countFiles(string $directory = '.'): int
    {
        /** @var array $contents */
        $contents = $this->filesystem->listContents($directory);
        $cnt = 0;

        foreach ($contents as $item) {
            if ($item['type'] === 'dir') {
                $cnt += $this->countFiles($item['path']);
            }

            if ($item['type'] === 'file') {
                if (strpos($item['basename'], '.') === 0) {
                    continue;
                }

                ++$cnt;
            }
        }

        return $cnt;
    }

    public function migrateFile(string $path): void
    {
        $filename = basename($path);
        $toPath = $this->toStrategy->encode($filename);

        $this->event->dispatch(MigrateAdvanceEvent::EVENT_NAME, new MigrateAdvanceEvent($filename));

        // file already exists
        if ($this->filesystem->has($toPath)) {
            $this->logger->debug('Skipped because target file already exists.', ['filename' => $filename, 'targetPath' => $toPath]);
            ++$this->statistics['skipped'];

            return;
        }

        // move file to new filesystem and remove the old one
        $this->filesystem->rename($path, $toPath);
        $this->logger->debug('Migrated file.', ['filename' => $filename, 'targetPath' => $toPath]);
        ++$this->statistics['migrated'];
    }

    private function migrateFiles(string $directory = '.')
    {
        /** @var array $contents */
        $contents = $this->filesystem->listContents($directory);

        foreach ($contents as $item) {
            if ($item['type'] === 'dir') {
                $this->migrateFiles($item['path']);
                continue;
            }

            if ($item['type'] === 'file') {
                if (strpos($item['basename'], '.') === 0) {
                    continue;
                }

                $this->migrateFile($item['path']);
            }
        }
    }
}
