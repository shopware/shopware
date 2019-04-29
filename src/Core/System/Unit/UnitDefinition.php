<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;

class UnitDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'unit';
    }

    public static function getCollectionClass(): string
    {
        return UnitCollection::class;
    }

    public static function getEntityClass(): string
    {
        return UnitEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new TranslatedField('shortCode'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('attributes'),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'unit_id', 'id'))->addFlags(new RestrictDelete(), new ReverseInherited('unit')),
            (new TranslationsAssociationField(UnitTranslationDefinition::class, 'unit_id'))->addFlags(new Required()),
        ]);
    }
}
