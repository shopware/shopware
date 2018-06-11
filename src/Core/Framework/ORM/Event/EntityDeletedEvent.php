<?php

namespace Shopware\Core\Framework\ORM\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityDefinition;

class EntityDeletedEvent extends EntityWrittenEvent implements DeletedEvent
{
    public function __construct(
        string $definition,
        array $ids,
        array $payload,
        array $existences,
        Context $context,
        array $errors = []
    ) {
        parent::__construct($definition, $ids, $payload, $existences, $context, $errors);

        /** @var string|EntityDefinition $definition */
        $this->name = $definition::getEntityName() . '.deleted';
    }
}