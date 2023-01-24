<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

/**
 * @package core
 *
 * @internal
 */
class SystemLocker
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function lock(): void
    {
        file_put_contents($this->projectDir . '/install.lock', date('YmdHi'));
    }
}
