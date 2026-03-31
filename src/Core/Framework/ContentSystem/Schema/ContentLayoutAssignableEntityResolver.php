<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutAssignableEntityResolver
{
    /**
     * @param list<string> $entityTypes
     */
    public function __construct(
        private readonly array $entityTypes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(): array
    {
        return $this->entityTypes;
    }
}
