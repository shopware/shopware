<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SystemLocker
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function lock(): void
    {
        // Ensure var directory exists
        if (!is_dir($this->projectDir . '/var')) {
            mkdir($this->projectDir . '/var', 0777, true);
        }

        file_put_contents($this->projectDir . '/var/install.lock', date('YmdHi'));
    }
}
