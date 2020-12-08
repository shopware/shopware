<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

class PathBuilder
{
    /**
     * @var string
     */
    private $baseDir;

    /**
     * @var string
     */
    private $sourceDir;

    /**
     * @var string
     */
    private $updateDirRelative;

    /**
     * @var string
     */
    private $backupDirRelative;

    /**
     * @param string $basePath
     * @param string $sourcePath
     * @param string $backupPath
     */
    public function __construct($basePath, $sourcePath, $backupPath)
    {
        $baseDir = rtrim($basePath, '/\\') . \DIRECTORY_SEPARATOR;
        $sourceDir = rtrim($sourcePath, '/\\') . \DIRECTORY_SEPARATOR;
        $backupDir = rtrim($backupPath, '/\\') . \DIRECTORY_SEPARATOR;

        $updateDirRelative = str_replace($baseDir, '', $sourceDir);
        $backupDirRelative = str_replace($baseDir, '', $backupDir);

        $this->sourceDir = $sourceDir;
        $this->baseDir = $basePath;

        $this->updateDirRelative = $updateDirRelative;
        $this->backupDirRelative = $backupDirRelative;
    }

    /**
     * @return string
     */
    public function getSourceDir()
    {
        return $this->sourceDir;
    }

    /**
     * @return string
     */
    public function getBackupDirRelative()
    {
        return $this->backupDirRelative;
    }

    /**
     * @return string
     */
    public function createTargetPath(\SplFileInfo $file)
    {
        return str_ireplace($this->sourceDir, '', $file->getPathname());
    }

    /**
     * @return string
     */
    public function createSourcePath(\SplFileInfo $file)
    {
        return $this->updateDirRelative . $this->createTargetPath($file);
    }

    /**
     * @return string
     */
    public function createBackupPath(\SplFileInfo $file)
    {
        return $this->backupDirRelative . $this->createTargetPath($file);
    }

    /**
     * @return string
     */
    public function getUpdateDirRelative()
    {
        return $this->updateDirRelative;
    }
}
