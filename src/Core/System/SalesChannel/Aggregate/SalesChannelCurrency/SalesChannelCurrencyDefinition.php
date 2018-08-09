<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency;

use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\MappingEntityDefinition;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelCurrencyDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_currency';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('currency_id', 'currency_id', CurrencyDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(CurrencyDefinition::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, false),
        ]);
    }
}
