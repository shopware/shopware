<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentLayoutAssignableEntitySchemaGenerator
{
    /**
     * @param list<string> $entityTypes
     */
    public function __construct(
        private readonly array $entityTypes,
    ) {
    }

    /**
     * @return array{entityTypes: list<string>}
     */
    public function getSchema(): array
    {
        return ['entityTypes' => $this->entityTypes];
    }
}
