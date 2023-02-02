<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;

#[Package('system-settings')]
class CustomFieldSetRelationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_field_set_relation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldSetRelationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldSetRelationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('set_id', 'customFieldSetId', CustomFieldSetDefinition::class))->addFlags(new Required()),
            (new StringField('entity_name', 'entityName', 63))->addFlags(new Required()),
            new ManyToOneAssociationField('customFieldSet', 'set_id', CustomFieldSetDefinition::class, 'id', false),
        ]);
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomFieldSetDefinition::class;
    }
}
