<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\EntityDefinition;

interface AssociationInterface
{
    public function loadInBasic(): bool;

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string;
}
