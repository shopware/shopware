<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Mock;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;

/**
 * @internal
 */
#[Package('discovery')]
class TestPlugin extends Plugin
{
    protected string $name;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
