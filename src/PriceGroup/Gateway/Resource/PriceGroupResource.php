<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Gateway\Resource;

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

class PriceGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('price_group');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Gateway\Resource\CustomerResource::class);
        $this->fields['discounts'] = new SubresourceField(\Shopware\PriceGroupDiscount\Gateway\Resource\PriceGroupDiscountResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Customer\Gateway\Resource\CustomerResource::class,
            \Shopware\PriceGroup\Gateway\Resource\PriceGroupResource::class,
            \Shopware\PriceGroupDiscount\Gateway\Resource\PriceGroupDiscountResource::class
        ];
    }
}
