<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CurrencyDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'currency';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CurrencyCollection::class;
    }

    public function getEntityClass(): string
    {
        return CurrencyEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FloatField('factor', 'factor'))->addFlags(new Required()),
            (new StringField('symbol', 'symbol'))->addFlags(new Required()),
            (new StringField('iso_code', 'isoCode'))->addFlags(new Required()),
            (new TranslatedField('shortName'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('decimal_precision', 'decimalPrecision'))->addFlags(new Required()),
            new IntField('position', 'position'),
            (new BoolField('is_system_default', 'isSystemDefault'))->addFlags(new Runtime()),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currency_id'))->addFlags(new Required()),

            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCurrencyDefinition::class, 'currency_id', 'sales_channel_id'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'currency_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('promotionDiscountPrices', PromotionDiscountPriceDefinition::class, 'currency_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}
