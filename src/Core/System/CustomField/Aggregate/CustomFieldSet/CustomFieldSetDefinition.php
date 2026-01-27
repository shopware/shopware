<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\Aggregate\CustomFieldSet;

use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

#[Package('framework')]
class CustomFieldSetDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_field_set';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldSetCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldSetEntity::class;
    }

    public function getDefaults(): array
    {
        return ['position' => 1];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom field set.'),
            (new StringField('name', 'name'))->addFlags(new Required(), new Immutable())->setDescription('Unique name of a custom field set.'),
            (new JsonField('config', 'config', [], []))->setDescription('Specifies detailed information about the component.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the custom field set is enabled for use.'),
            (new BoolField('global', 'global'))->setDescription('When set to `true`, the custom field set can be used across all sales channels.'),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined custom field set to be displayed.'),
            (new FkField('app_id', 'appId', AppDefinition::class))->setDescription('Unique identity of an app.'),

            (new OneToManyAssociationField('customFields', CustomFieldDefinition::class, 'set_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('relations', CustomFieldSetRelationDefinition::class, 'set_id'))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('products', ProductDefinition::class, ProductCustomFieldSetDefinition::class, 'custom_field_set_id', 'product_id'))->addFlags(new CascadeDelete(), new ReverseInherited('customFieldSets')),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
