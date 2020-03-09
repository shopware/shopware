<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount;

use Shopware\Core\Content\GoogleShopping\GoogleShoppingAccountDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GoogleShoppingMerchantAccountDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'google_shopping_merchant_account';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return GoogleShoppingMerchantAccountCollection::class;
    }

    public function getEntityClass(): string
    {
        return GoogleShoppingMerchantAccountEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('google_shopping_account_id', 'accountId', GoogleShoppingAccountDefinition::class))->addFlags(new Required()),
            (new StringField('merchant_id', 'merchantId'))->addFlags(new Required()),
            (new JsonField('custom_fields', 'customFields')),

            new OneToOneAssociationField('account', 'google_shopping_account_id', 'id', GoogleShoppingAccountDefinition::class, false),
        ]);
    }
}
