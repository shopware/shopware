<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Update\Services\Filesystem;
use Shopware\Core\Framework\Update\Struct\ValidationResult;

class WriteableCheck implements CheckerInterface
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $rootDir;

    public function __construct(Filesystem $filesystem, string $rootDir)
    {
        $this->filesystem = $filesystem;
        $this->rootDir = $rootDir;
    }

    public function supports(string $check): bool
    {
        return $check === 'writable';
    }

    /**
     * @param int|string|array $values
     */
    public function check($values): ValidationResult
    {
        $directories = [];
        $checkedDirectories = [];

        foreach ($values as $path) {
            $fullPath = rtrim($this->rootDir . '/' . $path, '/');
            $checkedDirectories[] = $fullPath;
            $fixPermissions = true;

            $directories = array_merge(
                $directories,
                $this->filesystem->checkSingleDirectoryPermissions($fullPath, $fixPermissions)
            );
        }

        if (empty($directories)) {
            return new ValidationResult(
                'writeableCheck',
                self::VALIDATION_SUCCESS,
                'writeableCheckValid',
                ['checkedDirectories' => implode('<br>', $checkedDirectories)]
            );
        }

        return new ValidationResult(
            'writeableCheck',
            self::VALIDATION_ERROR,
            'writeableCheckFailed',
            ['failedDirectories' => implode('<br>', $directories)]
        );
    }
}
