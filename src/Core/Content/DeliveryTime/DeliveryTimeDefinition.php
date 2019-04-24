<?php declare(strict_types=1);

namespace Shopware\Core\Content\DeliveryTime;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class DeliveryTimeDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'delivery_time';
    }

    public static function getEntityClass(): string
    {
        return DeliveryTimeEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return DeliveryTimeCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new TranslatedField('name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('min', 'min', 0))->addFlags(new Required()),
            (new IntField('max', 'max', 0))->addFlags(new Required()),
            (new StringField('unit', 'unit'))->addFlags(new Required()),
            new TranslatedField('customFields'),

            new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'delivery_time_id'),
            (new TranslationsAssociationField(DeliveryTimeTranslationDefinition::class, 'delivery_time_id'))->addFlags(new Required()),
        ]);
    }
}
