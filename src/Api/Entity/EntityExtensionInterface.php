<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

interface EntityExtensionInterface
{
    /**
     * Allows to add fields to an entity.
     *
     * To load fields by your own, add the \Shopware\Api\Entity\Write\Flag\Deferred flag to the field.
     * Added fields should have the \Shopware\Api\Entity\Write\Flag\Extension which tells the ORM that this data
     * is not include in the struct and collection classes
     *
     * @param FieldCollection $collection
     */
    public function extendFields(FieldCollection $collection);

    /**
     * Defines which entity definition should be extended by this class
     *
     * @return string
     */
    public function getDefinitionClass(): string;
}
