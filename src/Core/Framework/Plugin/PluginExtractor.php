<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginExtractionException;
use Shopware\Core\Framework\Plugin\Util\ZipUtils;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
class PluginExtractor
{
    /**
     * @param array<string, string> $extensionDirectories
     */
    public function __construct(
        private readonly array $extensionDirectories,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Extracts the provided zip file to the plugin directory
     */
    public function extract(string $zipFilePath, bool $delete, string $type): void
    {
        $archive = ZipUtils::openZip($zipFilePath);

        $destination = $this->extensionDirectories[$type];

        if (!is_writable($destination)) {
            throw new PluginExtractionException(sprintf('Destination directory "%s" is not writable', $destination));
        }

        $pluginName = $this->getPluginName($archive);
        $this->validatePluginZip($pluginName, $archive);

        $oldFile = $this->findOldFile($destination, $pluginName);
        $backupFile = $this->createBackupFile($oldFile);

        try {
            $archive->extractTo($destination);

            if ($backupFile !== null) {
                $this->filesystem->remove($backupFile);
            }

            if ($delete) {
                unlink($archive->filename);
            }
        } catch (\Exception $e) {
            $this->filesystem->rename($backupFile, $oldFile);

            throw $e;
        }

        $this->clearOpcodeCache();

        $archive->close();
    }

    /**
     * Iterates all files of the provided zip archive
     * path and validates the plugin namespace, directory traversal
     * and multiple plugin directories.
     */
    private function validatePluginZip(string $prefix, \ZipArchive $archive): void
    {
        $file = $prefix . '/manifest.xml';
        $manifestAsString = $archive->getFromName($file);
        if (\is_string($manifestAsString)) {
            Manifest::validate($manifestAsString, $file);
        }

        for ($i = 2; $i < $archive->numFiles; ++$i) {
            $stat = $archive->statIndex($i);
            \assert($stat !== false);

            $this->assertNoDirectoryTraversal($stat['name']);
            $this->assertPrefix($stat['name'], $prefix);
        }
    }

    private function getPluginName(\ZipArchive $archive): string
    {
        $entry = $archive->statIndex(0);
        \assert($entry !== false);

        return explode(\DIRECTORY_SEPARATOR, (string) $entry['name'])[0];
    }

    /**
     * Clear opcode caches to make sure that the
     * updated plugin files are used in the following requests.
     */
    private function clearOpcodeCache(): void
    {
        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        if (\function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }

    private function assertPrefix(string $filename, string $prefix): void
    {
        if (mb_strpos($filename, $prefix) !== 0) {
            throw new PluginExtractionException(
                sprintf(
                    'Detected invalid file/directory %s in the plugin zip: %s',
                    $filename,
                    $prefix
                )
            );
        }
    }

    private function assertNoDirectoryTraversal(string $filename): void
    {
        if (mb_strpos($filename, '..' . \DIRECTORY_SEPARATOR) !== false) {
            throw new PluginExtractionException('Directory Traversal detected');
        }
    }

    private function findOldFile(string $destination, string $pluginName): string
    {
        $dir = $destination . \DIRECTORY_SEPARATOR . $pluginName;
        if ($this->filesystem->exists($dir)) {
            return $dir;
        }

        return '';
    }

    private function createBackupFile(string $oldFile): ?string
    {
        if ($oldFile === '') {
            return null;
        }

        $backupFile = $oldFile . '.' . uniqid('', true);
        $this->filesystem->rename($oldFile, $backupFile);

        return $backupFile;
    }
}
