<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

class CleanupFilesFinder
{
    /**
     * @var string
     */
    private $shopwarePath;

    /**
     * @param string $shopwarePath
     */
    public function __construct($shopwarePath)
    {
        $this->shopwarePath = $shopwarePath;
    }

    /**
     * @return string[]
     */
    public function getCleanupFiles()
    {
        $cleanupFile = UPDATE_ASSET_PATH . '/cleanup.txt';
        if (!is_file($cleanupFile)) {
            return [];
        }

        $lines = file($cleanupFile, \FILE_IGNORE_NEW_LINES);

        $cleanupList = [];
        foreach ($lines as $path) {
            $realpath = $this->shopwarePath . '/' . $path;
            if (file_exists($realpath)) {
                $cleanupList[] = $realpath;
            }
        }

        return $cleanupList;
    }
}
