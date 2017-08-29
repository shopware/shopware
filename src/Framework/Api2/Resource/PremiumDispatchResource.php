<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class PremiumDispatchResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_premium_dispatch');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['type'] = (new IntField('type'))->setFlags(new Required());
        $this->fields['description'] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields['comment'] = (new StringField('comment'))->setFlags(new Required());
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['calculation'] = (new IntField('calculation'))->setFlags(new Required());
        $this->fields['surchargeCalculation'] = (new IntField('surcharge_calculation'))->setFlags(new Required());
        $this->fields['taxCalculation'] = (new IntField('tax_calculation'))->setFlags(new Required());
        $this->fields['shippingfree'] = new FloatField('shippingfree');
        $this->fields['multishopID'] = new IntField('multishopID');
        $this->fields['customergroupID'] = new IntField('customergroupID');
        $this->fields['bindShippingfree'] = (new IntField('bind_shippingfree'))->setFlags(new Required());
        $this->fields['bindTimeFrom'] = new IntField('bind_time_from');
        $this->fields['bindTimeTo'] = new IntField('bind_time_to');
        $this->fields['bindInstock'] = new IntField('bind_instock');
        $this->fields['bindLaststock'] = (new IntField('bind_laststock'))->setFlags(new Required());
        $this->fields['bindWeekdayFrom'] = new IntField('bind_weekday_from');
        $this->fields['bindWeekdayTo'] = new IntField('bind_weekday_to');
        $this->fields['bindWeightFrom'] = new FloatField('bind_weight_from');
        $this->fields['bindWeightTo'] = new FloatField('bind_weight_to');
        $this->fields['bindPriceFrom'] = new FloatField('bind_price_from');
        $this->fields['bindPriceTo'] = new FloatField('bind_price_to');
        $this->fields['bindSql'] = new LongTextField('bind_sql');
        $this->fields['statusLink'] = new LongTextField('status_link');
        $this->fields['calculationSql'] = new LongTextField('calculation_sql');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\PremiumDispatchResource::class
        ];
    }
}
