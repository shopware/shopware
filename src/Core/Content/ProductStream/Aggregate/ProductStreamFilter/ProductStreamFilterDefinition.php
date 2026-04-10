<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamFilterDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_stream_filter';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductStreamFilterEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductStreamFilterCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductStreamFilterHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ProductStreamDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of product stream filter.'),
            (new FkField('product_stream_id', 'productStreamId', ProductStreamDefinition::class))->addFlags(new Required())->setDescription('Unique identity of product stream.'),
            new ParentFkField(self::class),
            (new StringField('type', 'type'))->addFlags(new Required())->setDescription('Type is either `static` or `stream`.'),
            (new StringField('field', 'field'))->setDescription('Filter by field.'),
            (new StringField('operator', 'operator'))->setDescription('Filter by operator like >, ==, < , >=, etc.'),
            (new LongTextField('value', 'value'))->setDescription('Filter by ProductStreamFilter value.'),
            (new JsonField('parameters', 'parameters'))->setDescription('Additional parameters, such as specifying a date starting from a particular point.'),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined product stream filter to be displayed.'),
            new ManyToOneAssociationField('productStream', 'product_stream_id', ProductStreamDefinition::class, 'id', false),
            (new ParentAssociationField(self::class, 'id'))->setDescription('Unique identity of product stream filter.'),
            new ChildrenAssociationField(self::class, 'queries'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
