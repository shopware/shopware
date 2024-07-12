<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework;

use Shopware\Core\Framework\Bundle;

/**
 * @internal
 */
class BundleFixture extends Bundle
{
    public function __construct(
        string $name,
        string $path
    ) {
        $this->name = $name;
        $this->path = $path;
    }
}
