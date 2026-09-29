<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 *
 * Simulates a plugin extending an entity that already has a JSON schema override.
 */
class PluginExtensionForJsonOverride extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField(
                'pluginEntities',
                SimpleDefinition::class,
                'parent_id'
            ))->addFlags(new ApiAware())
        );
        $collection->add((new StringField('plugin_label', 'pluginLabel'))->addFlags(new ApiAware(), new Runtime()));
        $collection->add((new BoolField('plugin_active', 'pluginActive'))->addFlags(new ApiAware(), new Runtime()));
    }

    public function getEntityName(): string
    {
        return DefinitionWithJsonOverride::ENTITY_NAME;
    }
}
