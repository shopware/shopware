<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
class FileBackup
{
    public function __construct(private readonly string $filePath, private readonly Filesystem $filesystem = new Filesystem())
    {
    }

    public function restore(): void
    {
        if ($this->filesystem->exists($this->filePath . '.bak')) {
            $this->filesystem->copy($this->filePath . '.bak', $this->filePath, true);
            $this->remove();
        }
    }

    public function remove(): void
    {
        $this->filesystem->remove($this->filePath . '.bak');
    }

    public function backup(): void
    {
        $this->filesystem->copy($this->filePath, $this->filePath . '.bak');
    }
}
