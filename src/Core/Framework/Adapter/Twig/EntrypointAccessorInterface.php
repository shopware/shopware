<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Bundle as ShopwareBundle;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface EntrypointAccessorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getBundleData(ShopwareBundle $bundle): array;
}
