<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 *
 * @phpstan-type Snapshot array{version: string, files: array<string, string>}
 */
#[Package('discovery')]
class StorefrontSnippetStorage
{
    private const SOURCE_DIR = 'Resources/snippet';

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly SourceResolver $sourceResolver,
        private readonly LoggerInterface $logger,
        private readonly string $directory,
        private readonly Io $io = new Io(),
    ) {
    }

    public function directory(string $appName, string $version): ?string
    {
        $directory = Path::join($this->directory, $appName, Hasher::hash($version));
        if ($this->io->exists($directory . '/.complete')) {
            return $directory;
        }

        $stored = $this->read($appName);
        if ($stored !== null && $stored['version'] === $version) {
            $this->materialize($directory, $stored['files']);

            return $directory;
        }

        try {
            $source = $this->sourceResolver->filesystemForAppName($appName);
        } catch (\Throwable $e) {
            $this->logger->error('Could not load snippet files of app "{appName}"', ['appName' => $appName, 'exception' => $e]);

            return null;
        }

        $this->logger->info('Storefront snippets of app "{appName}" loaded from its source', ['appName' => $appName, 'location' => $source->location]);

        $files = $this->collect($source);
        $this->materialize($directory, $files);

        // A snapshot of another version is the lifecycle handler's to replace, never a request's.
        if ($stored === null) {
            try {
                $this->write($appName, $version, $files);
            } catch (\Throwable $e) {
                $this->logger->error('Could not persist storefront snippets of app "{appName}"', ['appName' => $appName, 'exception' => $e]);
            }
        }

        return $directory;
    }

    /**
     * @return bool whether snippet contents changed
     */
    public function persist(string $appName, string $version, Filesystem $appFilesystem): bool
    {
        $previousFiles = $this->read($appName)['files'] ?? [];
        $files = $this->collect($appFilesystem);
        $this->write($appName, $version, $files);

        return $previousFiles !== $files;
    }

    public function remove(string $appName): bool
    {
        $path = $this->path($appName);
        if (!$this->filesystem->fileExists($path)) {
            return false;
        }

        $this->filesystem->delete($path);

        return true;
    }

    /**
     * @return Snapshot|null
     */
    private function read(string $appName): ?array
    {
        try {
            $contents = $this->filesystem->read($this->path($appName));
        } catch (UnableToReadFile) {
            return null;
        }

        /** @var Snapshot $snapshot */
        $snapshot = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        return $snapshot;
    }

    /**
     * @return array<string, string>
     */
    private function collect(Filesystem $source): array
    {
        $files = [];
        if ($source->has(self::SOURCE_DIR)) {
            foreach ($source->findFiles('*.json', self::SOURCE_DIR) as $file) {
                $files[Path::join(self::SOURCE_DIR, $file->getRelativePathname())] = $file->getContents();
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @param array<string, string> $files
     */
    private function write(string $appName, string $version, array $files): void
    {
        $this->filesystem->write($this->path($appName), json_encode(['version' => $version, 'files' => $files], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $files
     */
    private function materialize(string $directory, array $files): void
    {
        foreach ($files as $path => $contents) {
            $this->io->dumpFile(Path::join($directory, $path), $contents);
        }
        // The marker goes last: readers accept the directory only once every file is in place.
        // Concurrent fills of one version write identical files atomically, so they need no lock.
        $this->io->dumpFile($directory . '/.complete', '');
    }

    private function path(string $appName): string
    {
        return 'translation/apps/' . $appName . '.json';
    }
}
