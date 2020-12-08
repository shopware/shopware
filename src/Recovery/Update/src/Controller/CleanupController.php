<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Recovery\Update\Cleanup;
use Shopware\Recovery\Update\CleanupFilesFinder;
use Shopware\Recovery\Update\Utils;
use Slim\App;

class CleanupController
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var CleanupFilesFinder
     */
    private $filesFinder;

    /**
     * @var string
     */
    private $shopwarePath;

    /**
     * @var string
     */
    private $backupDirectory;

    /**
     * @var Cleanup
     */
    private $cleanupService;

    /**
     * @param string $shopwarePath
     * @param string $backupDir
     */
    public function __construct(
        CleanupFilesFinder $filesFinder,
        Cleanup $cleanupService,
        App $app,
        $shopwarePath,
        $backupDir
    ) {
        $this->app = $app;
        $this->filesFinder = $filesFinder;
        $this->cleanupService = $cleanupService;
        $this->shopwarePath = $shopwarePath;
        $this->backupDirectory = $backupDir;
    }

    public function cleanupOldFiles(ServerRequestInterface $request, ResponseInterface $response)
    {
        $_SESSION['DB_DONE'] = true;

        $cleanupList = $this->getCleanupList();

        if (\count($cleanupList) === 0) {
            $_SESSION['CLEANUP_DONE'] = true;

            return $response->withRedirect($this->app->getContainer()->get('router')->pathFor('done'));
        }

        if ($request->getMethod() === 'POST') {
            $result = [];
            foreach ($cleanupList as $path) {
                $result = array_merge($result, Utils::cleanPath($path));
            }

            if (\count($result) === 0) {
                $_SESSION['CLEANUP_DONE'] = true;

                return $response->withRedirect($this->app->getContainer()->get('router')->pathFor('done'));
            }

            $result = array_map(
                static function ($path) {
                    return mb_substr($path, mb_strlen(SW_PATH) + 1);
                },
                $result
            );

            return $this->app->getContainer()->get('renderer')->render($response, 'cleanup.php', ['cleanupList' => $result, 'error' => true]);
        }

        $cleanupList = array_map(
            static function ($path) {
                return mb_substr($path, mb_strlen(SW_PATH) + 1);
            },
            $cleanupList
        );

        return $this->app->getContainer()->get('renderer')->render($response, 'cleanup.php', ['cleanupList' => $cleanupList, 'error' => false]);
    }

    /**
     * Deletes outdated folders from earlier shopware versions.
     */
    public function deleteOutdatedFolders(): void
    {
        echo $this->cleanupService->cleanup();
        exit();
    }

    /**
     * @param string $path
     *
     * @return array|\DirectoryIterator
     */
    private function getDirectoryIterator($path)
    {
        if (is_dir($path)) {
            return new \DirectoryIterator($path);
        }

        return [];
    }

    private function getCleanupList()
    {
        $cleanupList = $this->filesFinder->getCleanupFiles();

        $cacheDirectoryList = $this->getCacheDirectoryList();
        $cleanupList = array_merge(
            $cacheDirectoryList,
            $cleanupList
        );

        $temporaryBackupDirectories = $this->getTemporaryBackupDirectoryList();
        $cleanupList = array_merge(
            $temporaryBackupDirectories,
            $cleanupList
        );

        return $cleanupList;
    }

    /**
     * returns a array of directory names in the cache directory
     *
     * @return array
     */
    private function getCacheDirectoryList()
    {
        $cacheDirectories = $this->getDirectoryIterator($this->shopwarePath . '/var/cache');

        $directoryNames = [];
        foreach ($cacheDirectories as $directory) {
            if ($directory->isDot() || $directory->isFile()) {
                continue;
            }

            $directoryNames[] = $directory->getRealPath();
        }

        return $directoryNames;
    }

    private function getTemporaryBackupDirectoryList()
    {
        $directories = $this->getDirectoryIterator($this->backupDirectory);

        $directoryNames = [];
        foreach ($directories as $directory) {
            if ($directory->isDot() || $directory->isFile()) {
                continue;
            }

            $directoryNames[] = $directory->getRealPath();
        }

        return $directoryNames;
    }
}
