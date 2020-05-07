<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingAdsAccount;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GoogleShoppingAdsAccountDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'google_shopping_ads_account';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return GoogleShoppingAdsAccountCollection::class;
    }

    public function getEntityClass(): string
    {
        return GoogleShoppingAdsAccountEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('google_shopping_merchant_account_id', 'merchantAccountId', GoogleShoppingMerchantAccountDefinition::class))->addFlags(new Required()),
            (new StringField('ads_manager_id', 'adsManagerId'))->addFlags(new Required()),
            (new StringField('ads_id', 'adsId'))->addFlags(new Required()),
            new CustomFields(),

            new OneToOneAssociationField('merchantAccount', 'google_shopping_merchant_account_id', 'id', GoogleShoppingMerchantAccountDefinition::class, false),
        ]);
    }
}
