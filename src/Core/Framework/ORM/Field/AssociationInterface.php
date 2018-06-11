<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\EntityDefinition;

interface AssociationInterface
{
    public function getPropertyName(): string;

    public function loadInBasic(): bool;

    /**
     * @return string|EntityDefinition
     */
    public function getReferenceClass(): string;
}
