<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypesResolvedEvent extends Event
{
    /**
     * @param list<ContentSystemDataLoaderTypeDescriptor> $types
     */
    public function __construct(
        public readonly string $source,
        public array $types,
    ) {
    }
}
