<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime;

use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
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
use Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;

class DeliveryTimeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'delivery_time';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return DeliveryTimeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return DeliveryTimeCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new TranslatedField('name'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('min', 'min', 0))->addFlags(new Required()),
            (new IntField('max', 'max', 0))->addFlags(new Required()),
            (new StringField('unit', 'unit'))->addFlags(new Required()),
            new TranslatedField('customFields'),

            new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'delivery_time_id'),
            new OneToManyAssociationField('products', ProductDefinition::class, 'delivery_time_id'),
            (new TranslationsAssociationField(DeliveryTimeTranslationDefinition::class, 'delivery_time_id'))->addFlags(new Required()),
        ]);
    }
}
