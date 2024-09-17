<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class DefinitionValidatorFilterEvent
{
    /**
     * @param array<EntityDefinition> $entityDefinitions
     */
    public function __construct(
        private array $entityDefinitions,
    )
    {
    }

    public function filterDefinitions(callable $filter): void
    {
        $this->entityDefinitions = array_filter($this->entityDefinitions, $filter);
    }

    /**
     * @return array<EntityDefinition>
     */
    public function getEntityDefinitions(): array
    {
        return $this->entityDefinitions;
    }

}
