<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
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
     * Allows to modify fields of an entity.
     *
     * This method is called after all fields have been added to the entity.
     * You can use this method to modify fields. e.g. to modify flags
     * You can't add new fields with this method, use `extendFields()` for that.
     * Also removing fields is not possible.
     * Be aware, that removing flags from fields could cause corrupted data, if not taken with care.
     *
     * @see EntityExtension::extendFields() to add fields to an entity.
     */
    public function modifyFields(FieldCollection $collection): void
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

    abstract public function getEntityName(): string;
}
