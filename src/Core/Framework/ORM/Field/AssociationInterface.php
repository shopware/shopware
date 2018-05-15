<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Field;

use Shopware\Framework\ORM\EntityDefinition;

interface AssociationInterface
{
    public function loadInBasic(): bool;

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string;
}
