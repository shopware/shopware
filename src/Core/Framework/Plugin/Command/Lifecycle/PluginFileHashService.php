<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Command\Lifecycle;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Finder\Finder;

#[Package('core')]
class PluginFileHashService
{
    public const CHECKSUM_FILE = 'checksums.json';

    /**
     * @internal
     */
    public function __construct(
        private readonly string $rootDir,
    ) {
    }

    public function getChecksumFilePathForPlugin(PluginEntity $plugin): string
    {
        return "$this->rootDir/{$plugin->getPath()}" . self::CHECKSUM_FILE;
    }

    /**
     * @param string[] $fileExtensions
     */
    public function getChecksumData(PluginEntity $plugin, array $fileExtensions): PluginChecksumStruct
    {
        return PluginChecksumStruct::fromArray([
            'algorithm' => Hasher::ALGO,
            'fileExtensions' => $fileExtensions,
            'hashes' => $this->getHashes($plugin, $fileExtensions),
            'pluginVersion' => $plugin->getVersion(),
        ]);
    }

    public function checkPluginForChanges(PluginEntity $plugin): PluginChecksumCheckResult
    {
        $checksumFilePath = $this->getChecksumFilePathForPlugin($plugin);
        if (!is_file($checksumFilePath)) {
            return new PluginChecksumCheckResult(fileMissing: true);
        }

        $checksumFileContent = (string) file_get_contents($checksumFilePath);
        $checksumFileData = PluginChecksumStruct::fromArray(json_decode($checksumFileContent, true, 512, \JSON_THROW_ON_ERROR));

        $extensions = $checksumFileData->getFileExtensions();
        $checksumPluginVersion = $checksumFileData->getPluginVersion();

        if ($plugin->getVersion() !== $checksumPluginVersion) {
            return new PluginChecksumCheckResult(wrongVersion: true);
        }

        $currentHashes = $this->getHashes($plugin, $extensions, $checksumFileData->getAlgorithm());
        $previouslyHashedFiles = $checksumFileData->getHashes();

        $newFiles = array_diff_key($currentHashes, $previouslyHashedFiles);
        $missingFiles = array_diff_key($previouslyHashedFiles, $currentHashes);
        $manipulatedFiles = array_diff(array_diff_key($previouslyHashedFiles, $missingFiles, $newFiles), $currentHashes);

        return new PluginChecksumCheckResult(
            newFiles: array_keys($newFiles),
            changedFiles: array_keys($manipulatedFiles),
            missingFiles: array_keys($missingFiles),
        );
    }

    /**
     * @param string[] $extensions
     *
     * @return array<string, string>
     */
    private function getHashes(PluginEntity $plugin, array $extensions, ?string $algorithm = null): array
    {
        $algorithm = $algorithm ?? Hasher::ALGO;
        $pluginPath = $plugin->getPath();
        if ($pluginPath === null) {
            return [];
        }

        $directories = $this->getDirectories($plugin);

        $finder = new Finder();
        $finder->in($directories)->files()->name($extensions);

        $hashes = [];
        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            if (!\is_string($absoluteFilePath) || !$absoluteFilePath) {
                continue;
            }

            $relativePath = (string) str_replace($this->rootDir . '/' . $pluginPath, '', $absoluteFilePath);

            $hashes[$relativePath] = Hasher::hashFile($absoluteFilePath, $algorithm);
        }

        return $hashes;
    }

    /**
     * @return string[]
     */
    private function getDirectories(PluginEntity $plugin): array
    {
        $directories = [];

        $autoload = $plugin->getAutoload();
        $psr4 = $autoload['psr-4'] ?? [];
        foreach ($psr4 as $path) {
            if (\is_string($path) && $path !== '') {
                $directories[] = "$this->rootDir/{$plugin->getPath()}$path";
            }
        }

        return array_unique($directories);
    }
}
