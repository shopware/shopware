<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldDefinition;

#[Package('inventory')]
class ProductSearchConfigFieldDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_search_config_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSearchConfigFieldEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSearchConfigFieldCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    public function getDefaults(): array
    {
        return [
            'tokenize' => false,
            'searchable' => false,
            'ranking' => 0,
        ];
    }

    public function getHydratorClass(): string
    {
        return ProductSearchConfigFieldHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductSearchConfigDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Product Search Configuration field.'),
            (new FkField('product_search_config_id', 'searchConfigId', ProductSearchConfigDefinition::class))->addFlags(new Required())->setDescription('Unique identity of Search Configuration.'),
            (new FkField('custom_field_id', 'customFieldId', CustomFieldDefinition::class))->setDescription('Unique identity of custom field.'),
            (new StringField('field', 'field'))->addFlags(new Required())->setDescription('Configuration of search field.'),
            (new BoolField('tokenize', 'tokenize'))->addFlags(new Required())->setDescription('To decide whether the text within the field should undergo tokenization, which involves splitting it into smaller chunks.'),
            (new BoolField('searchable', 'searchable'))->addFlags(new Required())->setDescription('To configure whether the field can be used for searching.'),
            (new IntField('ranking', 'ranking'))->addFlags(new Required())->setDescription('Search ranking.'),
            new ManyToOneAssociationField('searchConfig', 'product_search_config_id', ProductSearchConfigDefinition::class, 'id', false),
            new ManyToOneAssociationField('customField', 'custom_field_id', CustomFieldDefinition::class, 'id', false),
        ]);
    }
}
