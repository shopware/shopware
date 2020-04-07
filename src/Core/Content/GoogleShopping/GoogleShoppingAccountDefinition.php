<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountDefinition;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\Field\GoogleAccountCredentialField;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class GoogleShoppingAccountDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'google_shopping_account';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return GoogleShoppingAccountCollection::class;
    }

    public function getEntityClass(): string
    {
        return GoogleShoppingAccountEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            (new StringField('email', 'email'))->addFlags(new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new GoogleAccountCredentialField('credential', 'credential'))->addFlags(new Required()),
            (new JsonField('custom_fields', 'customFields')),

            new OneToOneAssociationField('salesChannel', 'sales_channel_id', 'id', SalesChannelDefinition::class, false),
            (new OneToOneAssociationField('googleShoppingMerchantAccount', 'id', 'google_shopping_account_id', GoogleShoppingMerchantAccountDefinition::class, false))->addFlags(new CascadeDelete()),
        ]);
    }
}
