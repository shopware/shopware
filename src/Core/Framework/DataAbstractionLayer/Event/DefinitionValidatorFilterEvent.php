<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

#[Package('core')]
class DefinitionValidatorFilterEvent
{
    /**
     * @param array<EntityDefinition> $entityDefinitions
     */
    public function __construct(
        public array $entityDefinitions,
    ) {
    }

    public function filterDefinitions(callable $filter): void
    {
        $this->entityDefinitions = array_filter($this->entityDefinitions, $filter);
    }
}
