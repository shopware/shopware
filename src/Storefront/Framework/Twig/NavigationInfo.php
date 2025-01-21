<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
final readonly class NavigationInfo
{
    /**
     * @param list<string> $pathIdList
     */
    public function __construct(
        public string $id,
        public array $pathIdList,
    ) {
    }
}
