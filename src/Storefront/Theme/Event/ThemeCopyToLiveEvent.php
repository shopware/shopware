<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeCopyToLiveEvent extends Event
{
    private string $themeId;

    private string $path;

    private string $backupPath;

    private string $tmpPath;

    public function __construct(string $themeId, string $path, string $backupPath, string $tmpPath)
    {
        $this->themeId = $themeId;
        $this->path = $path;
        $this->backupPath = $backupPath;
        $this->tmpPath = $tmpPath;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    public function getTmpPath(): string
    {
        return $this->tmpPath;
    }

    public function setTmpPath(string $tmpPath): void
    {
        $this->tmpPath = $tmpPath;
    }

    public function setBackupPath(string $backupPath): void
    {
        $this->backupPath = $backupPath;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
