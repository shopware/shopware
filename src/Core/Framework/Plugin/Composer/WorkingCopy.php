<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Autoload\ClassLoader;
use Composer\Json\JsonManipulator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Message\CleanupOldVendorDirectories;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class WorkingCopy
{
    public const LOCK_KEY = 'plugin-composer-working-copy';

    /**
     * Staged and replaced directories are siblings of `vendor/`, so the relative paths in the generated
     * autoload files and the relative symlinks of path repositories stay valid after the rename.
     */
    private const STAGING_PREFIX = '.vendor-staging-';

    private const OLD_PREFIX = '.vendor-old-';

    private const STAGED_COMPOSER_PREFIX = 'composer.staging-';

    private const SWAP_MARKER = '.composer-swap-in-progress';

    private const LOCK_TTL = 3600;

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
        private readonly LockFactory $lockFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param \Closure(string): int $operation receives the staged composer.json, which `COMPOSER` points to while it runs
     *                                         and which configures the staged vendor directory as its `vendor-dir`
     */
    public function run(\Closure $operation): int
    {
        $lock = $this->lockFactory->createLock(self::LOCK_KEY, self::LOCK_TTL);
        if (!$lock->acquire(false)) {
            throw PluginException::composerOperationInProgress();
        }

        try {
            $this->assertVendorDirectoryCanBeReplaced();
            $this->removeDirectories(self::STAGING_PREFIX);
            $this->removeDirectories(self::OLD_PREFIX);
            $this->filesystem->remove($this->stagedComposerFiles());

            $swapId = bin2hex(random_bytes(8));
            $stagingDir = $this->projectDir . '/' . self::STAGING_PREFIX . $swapId;
            $stagedComposerJson = $this->projectDir . '/' . self::STAGED_COMPOSER_PREFIX . $swapId . '.json';
            $stagedComposerLock = $this->projectDir . '/' . self::STAGED_COMPOSER_PREFIX . $swapId . '.lock';

            $hasReplacedVendorDirectory = false;

            try {
                $this->filesystem->mirror($this->vendorDir(), $stagingDir);
                $this->filesystem->copy($this->projectDir . '/composer.json', $stagedComposerJson, true);
                $this->addStagedVendorDir($stagedComposerJson, $stagingDir);
                if (is_file($this->projectDir . '/composer.lock')) {
                    $this->filesystem->copy($this->projectDir . '/composer.lock', $stagedComposerLock, true);
                }

                try {
                    $exitCode = $this->withEnvironment(
                        ['COMPOSER' => $stagedComposerJson],
                        static fn (): int => $operation($stagedComposerJson),
                    );
                } finally {
                    $this->unregisterClassLoaders($stagingDir);
                }

                if ($exitCode !== 0) {
                    return $exitCode;
                }

                $this->assertStagedVendorDirectoryIsComplete($stagingDir);
                $this->removeStagedVendorDir($stagedComposerJson);
                $this->replace($swapId, $stagingDir, $stagedComposerJson, $stagedComposerLock);
                $hasReplacedVendorDirectory = true;
            } finally {
                if (!$hasReplacedVendorDirectory) {
                    $this->filesystem->remove([$stagingDir, $stagedComposerJson, $stagedComposerLock]);
                }
            }
        } finally {
            $lock->release();
        }

        $this->messageBus->dispatch(new CleanupOldVendorDirectories());

        return 0;
    }

    public function removeOldVendorDirectories(): void
    {
        $lock = $this->lockFactory->createLock(self::LOCK_KEY, self::LOCK_TTL);
        if (!$lock->acquire(false)) {
            return;
        }

        try {
            $this->removeDirectories(self::OLD_PREFIX);
        } finally {
            $lock->release();
        }
    }

    private function vendorDir(): string
    {
        return $this->projectDir . '/vendor';
    }

    private function assertVendorDirectoryCanBeReplaced(): void
    {
        $swapMarkerPath = $this->projectDir . '/' . self::SWAP_MARKER;
        if (is_file($swapMarkerPath)) {
            throw PluginException::composerWorkingCopyInterrupted($swapMarkerPath);
        }

        $composerJson = json_decode((string) file_get_contents($this->projectDir . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        $configuredVendorDir = \is_array($composerJson) ? ($composerJson['config']['vendor-dir'] ?? 'vendor') : 'vendor';
        if ($configuredVendorDir !== 'vendor') {
            throw PluginException::composerWorkingCopyNotPossible(\sprintf('composer.json configures the vendor directory "%s"', $configuredVendorDir));
        }

        $vendorDir = $this->vendorDir();
        if (!is_dir($vendorDir) || is_link($vendorDir)) {
            throw PluginException::composerWorkingCopyNotPossible(\sprintf('"%s" is not a directory', $vendorDir));
        }

        if (!is_writable($this->projectDir)) {
            throw PluginException::composerWorkingCopyNotPossible(\sprintf('"%s" is not writable', $this->projectDir));
        }

        $vendorDirStat = stat($vendorDir);
        $projectDirStat = stat($this->projectDir);
        if ($vendorDirStat === false || $projectDirStat === false || $vendorDirStat['dev'] !== $projectDirStat['dev']) {
            throw PluginException::composerWorkingCopyNotPossible(\sprintf('"%s" is a mount point', $vendorDir));
        }
    }

    private function assertStagedVendorDirectoryIsComplete(string $stagingDir): void
    {
        $requiredFiles = ['autoload.php', 'composer/installed.php'];
        if (is_file($this->vendorDir() . '/autoload_runtime.php')) {
            $requiredFiles[] = 'autoload_runtime.php';
        }

        foreach ($requiredFiles as $file) {
            if (!is_file($stagingDir . '/' . $file)) {
                throw PluginException::composerWorkingCopyNotPossible(\sprintf('Composer did not write "%s" into the working copy', $file));
            }
        }
    }

    /**
     * A request that boots between the two renames of `vendor/` finds no autoloader. The marker blocks
     * further operations when the process dies in between, because the live files may no longer match.
     */
    private function replace(string $swapId, string $stagingDir, string $stagedComposerJson, string $stagedComposerLock): void
    {
        $swapMarkerPath = $this->projectDir . '/' . self::SWAP_MARKER;
        $vendorDir = $this->vendorDir();
        $oldDir = $this->projectDir . '/' . self::OLD_PREFIX . $swapId;

        $this->filesystem->dumpFile($swapMarkerPath, $swapId);

        try {
            $this->filesystem->rename($vendorDir, $oldDir);
            try {
                $this->filesystem->rename($stagingDir, $vendorDir);
            } catch (IOException $e) {
                $this->filesystem->rename($oldDir, $vendorDir);

                throw $e;
            }

            try {
                if (is_file($stagedComposerLock)) {
                    $this->filesystem->rename($stagedComposerLock, $this->projectDir . '/composer.lock', true);
                }
            } catch (IOException $e) {
                $this->filesystem->rename($vendorDir, $stagingDir);
                $this->filesystem->rename($oldDir, $vendorDir);

                throw $e;
            }
        } catch (IOException $e) {
            $this->filesystem->remove($swapMarkerPath);

            throw PluginException::composerWorkingCopyNotPossible($e->getMessage());
        }

        $this->filesystem->rename($stagedComposerJson, $this->projectDir . '/composer.json', true);
        $this->filesystem->remove($swapMarkerPath);
    }

    /**
     * Composer registers class loaders for its plugins and event listeners in this process. They point into the
     * working copy, which no longer exists under that path once it was renamed or removed.
     */
    private function unregisterClassLoaders(string $stagingDir): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if (str_starts_with($vendorDir, $stagingDir)) {
                $loader->unregister();
            }
        }
    }

    private function removeDirectories(string $prefix): void
    {
        $prefixedDirectories = glob($this->projectDir . '/' . $prefix . '*', \GLOB_ONLYDIR | \GLOB_NOSORT);
        if ($prefixedDirectories === false || $prefixedDirectories === []) {
            return;
        }

        $this->filesystem->remove($prefixedDirectories);
    }

    /**
     * @return list<string>
     */
    private function stagedComposerFiles(): array
    {
        $files = glob($this->projectDir . '/' . self::STAGED_COMPOSER_PREFIX . '*', \GLOB_NOSORT);

        return $files === false ? [] : $files;
    }

    /**
     * `COMPOSER_VENDOR_DIR` is not used, because Composer applies it to the global Composer instance as well, which
     * then treats the working copy as its own vendor directory.
     */
    private function addStagedVendorDir(string $stagedComposerJson, string $stagingDir): void
    {
        $composerJson = new JsonManipulator((string) file_get_contents($stagedComposerJson));
        if (!$composerJson->addConfigSetting('vendor-dir', $stagingDir)) {
            throw PluginException::composerWorkingCopyNotPossible('composer.json cannot be edited to configure the vendor directory');
        }

        $this->filesystem->dumpFile($stagedComposerJson, $composerJson->getContents());
    }

    private function removeStagedVendorDir(string $stagedComposerJson): void
    {
        $composerJson = new JsonManipulator((string) file_get_contents($stagedComposerJson));
        $isVendorDirRemoved = $composerJson->removeConfigSetting('vendor-dir');

        $liveComposerJson = json_decode((string) file_get_contents($this->projectDir . '/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($liveComposerJson) || !\array_key_exists('config', $liveComposerJson)) {
            $isVendorDirRemoved = $isVendorDirRemoved && $composerJson->removeMainKeyIfEmpty('config');
        }

        if (!$isVendorDirRemoved) {
            throw PluginException::composerWorkingCopyNotPossible('composer.json cannot be edited to remove the staged vendor directory');
        }

        $this->filesystem->dumpFile($stagedComposerJson, $composerJson->getContents());
    }

    /**
     * Composer reads its environment through `$_SERVER`, `$_ENV` and `getenv()`.
     *
     * @param array<string, string> $variables
     * @param \Closure(): int $operation
     */
    private function withEnvironment(array $variables, \Closure $operation): int
    {
        $previousValuesByName = [];
        foreach ($variables as $name => $value) {
            $previousValuesByName[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }

        try {
            return $operation();
        } finally {
            foreach ($previousValuesByName as $name => [$server, $env, $process]) {
                if ($server === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $server;
                }

                if ($env === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $env;
                }

                putenv($process === false ? $name : $name . '=' . $process);
            }
        }
    }
}
