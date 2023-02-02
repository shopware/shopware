<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

/**
 * @internal
 */
class SystemLocker
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function lock(): void
    {
        file_put_contents($this->projectDir . '/install.lock', date('YmdHi'));
    }
}
