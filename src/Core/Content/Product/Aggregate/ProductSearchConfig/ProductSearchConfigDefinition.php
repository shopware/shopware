<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Language\LanguageDefinition;

class ProductSearchConfigDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product_search_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductSearchConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductSearchConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    public function getDefaults(): array
    {
        return [
            'andLogic' => true,
            'minSearchLength' => 2,
            'excludedTerms' => [],
        ];
    }

    public function getHydratorClass(): string
    {
        return ProductSearchConfigHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            (new BoolField('and_logic', 'andLogic'))->addFlags(new Required()),
            (new IntField('min_search_length', 'minSearchLength'))->addFlags(new Required()),
            new ListField('excluded_terms', 'excludedTerms', StringField::class),
            new OneToOneAssociationField('language', 'language_id', 'id', LanguageDefinition::class, false),
            (new OneToManyAssociationField('configFields', ProductSearchConfigFieldDefinition::class, 'product_search_config_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
