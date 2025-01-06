<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\Feature;
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
     * @deprecated tag:v6.7.0 - Implement `getEntityName` instead or use `BulkEntityExtension`
     * Defines which entity definition should be extended by this class.
     *
     * When removing this method. Make sure to remove all child implementations
     */
    abstract public function getDefinitionClass(): string;

    /**
     * @deprecated tag:v6.7.0 - reason:visibility-change - Becomes abstract
     */
    public function getEntityName(): string
    {
        if (EnvironmentHelper::getVariable('APP_ENV') === 'dev') {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0', 'Method will be abstract'));
        }

        return '';
    }
}
