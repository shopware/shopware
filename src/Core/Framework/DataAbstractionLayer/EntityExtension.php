<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class EntityExtension
{
    /**
     * Allows to add fields to an entity.
     *
     * To load fields by your own, add the \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime flag to the field.
     * Added fields should have the \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension which tells the DAL that this data
     * is not include in the struct and collection classes
     */
    public function extendFields(FieldCollection $collection): void
    {
    }

    /**
     * Allows to add protections to an entity
     *
     * Add the protections you need to the given `$protections`
     */
    public function extendProtections(EntityProtectionCollection $protections): void
    {
    }

    /**
     * Defines which entity definition should be extended by this class
     */
    abstract public function getDefinitionClass(): string;
}
