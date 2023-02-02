<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation;

use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ProductStreamTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'product_stream_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductStreamTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductStreamTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ProductStreamDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
