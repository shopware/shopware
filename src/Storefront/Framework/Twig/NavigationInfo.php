<?php

declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class NavigationInfo
{
    /**
     * @deprecated tag:v6.7.0 - The pathIdList parameter is mandatory and will be made required and readonly in v6.7.0.0.
     *
     * @param list<string>|null $pathIdList
     */
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public /* readonly */ ?array $pathIdList = null,
    ) {
        if ($this->pathIdList === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'The pathIdList property is mandatory and will be made required in v6.7.0.0.',
            );
            $this->pathIdList = array_values(array_filter(explode('|', $this->path)));
        }
    }
}
