<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelCountryDefinition extends MappingEntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel_country';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new PrimaryKey(), new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
        ]);
    }
}
