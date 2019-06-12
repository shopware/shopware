<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

interface EntityExtensionInterface
{
    /**
     * Allows to add fields to an entity.
     *
     * To load fields by your own, add the \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime flag to the field.
     * Added fields should have the \Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension which tells the DAL that this data
     * is not include in the struct and collection classes
     */
    public function extendFields(FieldCollection $collection): void;

    /**
     * Defines which entity definition should be extended by this class
     */
    public function getDefinitionClass(): string;
}
