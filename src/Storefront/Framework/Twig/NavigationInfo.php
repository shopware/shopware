<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class NavigationInfo
{
    public function __construct(
        public string $id,
        public string $path,
    ) {
    }
}
