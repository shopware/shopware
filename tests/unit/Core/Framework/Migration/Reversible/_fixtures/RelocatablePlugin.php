<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;

/**
 * Plugin::getPath() resolves from the class file location, so a plugin fixture always points back
 * into the repository. This subclass lets a test redirect it at a writable directory instead.
 *
 * @internal
 */
#[Package('framework')]
class RelocatablePlugin extends Plugin
{
    private ?string $overriddenPath = null;

    public function relocateTo(string $path): self
    {
        $this->overriddenPath = $path;

        return $this;
    }

    public function getPath(): string
    {
        return $this->overriddenPath ?? parent::getPath();
    }

    public function getNamespace(): string
    {
        return 'Swag\\Reversible';
    }
}
