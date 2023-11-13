<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;

#[Package('inventory')]
class ProductKeywordDictionaryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'product_keyword_dictionary';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductKeywordDictionaryCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductKeywordDictionaryEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ProductKeywordDictionaryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StringField('keyword', 'keyword'))->addFlags(new ApiAware(), new Required()),
            (new StringField('reversed', 'reversed'))->addFlags(new Computed()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
