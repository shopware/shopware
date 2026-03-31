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
    public function __construct(
        private readonly ContentLayoutAssignableEntityResolver $resolver,
    ) {
    }

    /**
     * @return array{entityTypes: list<string>}
     */
    public function getSchema(): array
    {
        return ['entityTypes' => $this->resolver->resolve()];
    }
}
