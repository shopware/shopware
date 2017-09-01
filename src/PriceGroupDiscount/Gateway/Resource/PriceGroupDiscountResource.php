<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Gateway\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class PriceGroupDiscountResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const DISCOUNT_FIELD = 'discount';
    protected const DISCOUNT_START_FIELD = 'discountStart';

    public function __construct()
    {
        parent::__construct('price_group_discount');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::DISCOUNT_FIELD] = (new FloatField('discount'))->setFlags(new Required());
        $this->fields[self::DISCOUNT_START_FIELD] = (new FloatField('discount_start'))->setFlags(new Required());
        $this->fields['priceGroup'] = new ReferenceField('priceGroupUuid', 'uuid', \Shopware\PriceGroup\Gateway\Resource\PriceGroupResource::class);
        $this->fields['priceGroupUuid'] = (new FkField('price_group_uuid', \Shopware\PriceGroup\Gateway\Resource\PriceGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\PriceGroup\Gateway\Resource\PriceGroupResource::class,
            \Shopware\CustomerGroup\Gateway\Resource\CustomerGroupResource::class,
            \Shopware\PriceGroupDiscount\Gateway\Resource\PriceGroupDiscountResource::class
        ];
    }
}
