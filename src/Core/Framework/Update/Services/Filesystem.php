<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('system-settings')]
class Filesystem
{
    /**
     * @return array<string>
     */
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
        chmod($fileInfo->getPathname(), (int) $newPermission);
        clearstatcache(false, $fileInfo->getPathname());
    }
}
