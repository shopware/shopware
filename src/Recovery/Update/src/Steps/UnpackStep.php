<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Steps;

use Gaufrette\Filesystem;
use Shopware\Recovery\Common\Steps\FinishResult;
use Shopware\Recovery\Common\Steps\ValidResult;
use Shopware\Recovery\Update\pathBuilder;

class UnpackStep
{
    /**
     * @var \Gaufrette\Filesystem
     */
    private $localFilesyste;

    /**
     * @var \Gaufrette\Filesystem
     */
    private $remoteFilesyste;

    /**
     * @var PathBuilder
     */
    private $pathBuilder;

    /**
     * @var bool
     */
    private $isDebug;

    /**
     * @param bool $isDebug
     */
    public function __construct(Filesystem $localFilesyste, Filesystem $remoteFilesyste, PathBuilder $pathBuilder, $isDebug = false)
    {
        $this->localFilesyste = $localFilesyste;
        $this->remoteFilesyste = $remoteFilesyste;
        $this->pathBuilder = $pathBuilder;
        $this->isDebug = $isDebug;
    }

    /**
     * @param int $offset
     * @param int $total
     *
     * @throws \RuntimeException
     *
     * @return FinishResult|ValidResult
     */
    public function run($offset, $total)
    {
        $inflector = $this->pathBuilder;

        $remoteFs = $this->remoteFilesyste;
        $localFs = $this->localFilesyste;

        $backupDirRelative = $inflector->getBackupDirRelative();

        if ($offset === 0) {
            if ($localFs->has($backupDirRelative)) {
                $localFs->rename($backupDirRelative, rtrim($backupDirRelative, '/') . uniqid());
            }

            // Maybe we have to create backup dir here:
            $localFs->write($backupDirRelative . 'dummy', 'dummyfile');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($inflector->getSourceDir(), \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        if (!$total) {
            $total = iterator_count($iterator);
        }

        $count = 0;
        $maxCount = 5000;
        $startTime = time();

        /** @var \SplFileInfo $path */
        foreach ($iterator as $path) {
            if (strpos($path->getPathname(), 'vendor/shopware/recovery/') !== false) {
                continue;
            }

            $targetFile = $inflector->createTargetPath($path);
            $backupFile = $inflector->createBackupPath($path);
            $sourceFile = $inflector->createSourcePath($path);

            if (time() - $startTime >= 5 || $count >= $maxCount) {
                return new ValidResult($offset + $count + 1, $total);
            }

            ++$count;

            if ($this->isDebug) {
                // Just remove the update file
                $localFs->delete($sourceFile);
            } else {
                if ($localFs->has($targetFile)) {
                    if ($localFs->has($backupFile)) {
                        // Issue rename to trash command
                        $remoteFs->delete($targetFile);
                    } else {
                        $remoteFs->rename($targetFile, $backupFile);
                    }
                }

                $remoteFs->rename($sourceFile, $targetFile);
            }
        }

        return new FinishResult($total, $total);
    }
}
