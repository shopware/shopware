<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;

#[Package('framework')]
class CustomFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'custom_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'allowCustomerWrites' => false,
            'allowCartExpose' => false,
            'storeApiAware' => true,
            'includeInSearch' => false,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom field.'),
            (new StringField('name', 'name'))->addFlags(new Required(), new Immutable())->setDescription('Unique name of a custom field.'),
            (new StringField('type', 'type'))->addFlags(new Required(), new Immutable())->setDescription('Custom field type can be selection, media , etc'),
            (new JsonField('config', 'config', [], []))->setDescription('Specifies detailed information about the component.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the custom field is enabled for use.'),
            (new FkField('set_id', 'customFieldSetId', CustomFieldSetDefinition::class))->setDescription('Unique identity of customFieldSet.'),
            (new BoolField('allow_customer_write', 'allowCustomerWrite'))->setDescription('When boolean value is `true`, then customers have permission to write data in the custom field.'),
            (new BoolField('allow_cart_expose', 'allowCartExpose'))->setDescription('When boolean value is `true`, then the custom field\'s data can be exposed within the shopping cart or order process.'),
            new BoolField('store_api_aware', 'storeApiAware'),
            new BoolField('include_in_search', 'includeInSearch'),
            new ManyToOneAssociationField('customFieldSet', 'set_id', CustomFieldSetDefinition::class, 'id', false),
            (new OneToManyAssociationField('productSearchConfigFields', ProductSearchConfigFieldDefinition::class, 'custom_field_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
