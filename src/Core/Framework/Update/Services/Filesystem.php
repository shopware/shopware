<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

class Filesystem
{
    /**
     * @var string[]
     */
    private $VCSDirs = [
        '.git',
        '.svn',
    ];

    public function checkSingleDirectoryPermissions(string $directory, bool $fixPermission = false): array
    {
        $errors = [];

        if (!is_dir($directory) && !mkdir($directory) && !is_dir($directory)) {
            $errors[] = $directory;

            return $errors;
        }
        if ($fixPermission && !is_writable($directory)) {
            $fileInfo = new \SplFileInfo($directory);
            $this->fixDirectoryPermission($fileInfo);
        }
        if (!is_writable($directory)) {
            $errors[] = $directory;

            return $errors;
        }

        return $errors;
    }

    public function checkDirectoryPermissions(string $directory, bool $fixPermission = false): array
    {
        $errors = $this->checkSingleDirectoryPermissions($directory, $fixPermission);
        if (!empty($errors)) {
            return $errors;
        }
        foreach (new \DirectoryIterator($directory) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            if ($fileInfo->isFile()) {
                if ($fixPermission && !$fileInfo->isWritable()) {
                    $this->fixFilePermission($fileInfo);
                }
                if (!$fileInfo->isWritable()) {
                    $errors[] = $fileInfo->getPathname();
                }

                continue;
            }
            // skip VCS dirs
            if (\in_array($fileInfo->getBasename(), $this->VCSDirs, true)) {
                continue;
            }
            if ($fixPermission && !$fileInfo->isWritable()) {
                $this->fixDirectoryPermission($fileInfo);
            }
            if (!$fileInfo->isWritable()) {
                $errors[] = $fileInfo->getPathname();

                continue;
            }
            $errors = array_merge($errors, $this->checkDirectoryPermissions($fileInfo->getPathname(), $fixPermission));
        }

        return $errors;
    }

    private function fixDirectoryPermission(\SplFileInfo $fileInfo): void
    {
        try {
            $permission = mb_substr(sprintf('%o', $fileInfo->getPerms()), -4);
        } catch (\Exception $e) {
            // cannot get permissions...
            return;
        }
        $newPermission = $permission;
        // set owner-bit to writable
        $newPermission[1] = '7';
        // set group-bit to writable
        $newPermission[2] = '7';
        $newPermission = octdec($newPermission);
        chmod($fileInfo->getPathname(), $newPermission);
        clearstatcache(false, $fileInfo->getPathname());
    }

    private function fixFilePermission(\SplFileInfo $fileInfo): void
    {
        try {
            $permission = mb_substr(sprintf('%o', $fileInfo->getPerms()), -4);
        } catch (\Exception $e) {
            // cannot get permissions...
            return;
        }
        $newPermission = $permission;
        // set owner-bit to writable
        $newPermission[1] = '6';
        // set group-bit to writable
        $newPermission[2] = '6';
        if ($fileInfo->isExecutable()) {
            // set owner-bit to writable/executable
            $newPermission[1] = '7';
            // set group-bit to writable/executable
            $newPermission[2] = '7';
        }
        $newPermission = octdec($newPermission);
        chmod($fileInfo->getPathname(), $newPermission);
        clearstatcache(false, $fileInfo->getPathname());
    }
}
