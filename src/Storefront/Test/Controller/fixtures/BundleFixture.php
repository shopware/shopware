<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller\fixtures;

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
