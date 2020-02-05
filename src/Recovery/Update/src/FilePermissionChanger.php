<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

/**
 * Changes the permissions defined in the given array.
 */
class FilePermissionChanger
{
    /**
     * Format:
     * [
     *      ['chmod' => 0755, 'filePath' => '/path/to/some/file'],
     * ]
     *
     * @var array
     */
    private $filePermissions = [];

    /**
     * @param array
     */
    public function __construct(array $filePermissions)
    {
        $this->filePermissions = $filePermissions;
    }

    /**
     * Performs the chmod command on all permission arrays previously provided.
     */
    public function changePermissions(): void
    {
        foreach ($this->filePermissions as $filePermission) {
            if (array_key_exists('filePath', $filePermission)
                && array_key_exists('chmod', $filePermission)
                && is_writable($filePermission['filePath'])) {
                // If the owner of a file is not the user of the currently running process, "is_writable" might return true
                // while "chmod" below fails. So we suppress any errors in that case.

                try {
                    @chmod($filePermission['filePath'], $filePermission['chmod']);
                } catch (\Exception $e) {
                    // Don't block the update process
                } catch (\Throwable $e) {
                    // Don't block the update process
                }
            }
        }
    }
}
