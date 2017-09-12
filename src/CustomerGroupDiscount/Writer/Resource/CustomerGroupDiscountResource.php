<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Writer\Resource;

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

class CustomerGroupDiscountResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const DISCOUNT_FIELD = 'discount';
    protected const DISCOUNT_START_FIELD = 'discountStart';

    public function __construct()
    {
        parent::__construct('customer_group_discount');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::DISCOUNT_FIELD] = (new FloatField('discount'))->setFlags(new Required());
        $this->fields[self::DISCOUNT_START_FIELD] = (new FloatField('discount_start'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\CustomerGroupDiscount\Writer\Resource\CustomerGroupDiscountResource::class
        ];
    }
}
