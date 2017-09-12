<?php declare(strict_types=1);

namespace Shopware\Holiday\Writer;

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

class HolidayResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const CALCULATION_FIELD = 'calculation';
    protected const DATE_FIELD = 'date';

    public function __construct()
    {
        parent::__construct('holiday');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::CALCULATION_FIELD] = (new StringField('calculation'))->setFlags(new Required());
        $this->fields[self::DATE_FIELD] = (new DateField('date'))->setFlags(new Required());
        $this->fields['shippingMethodHolidays'] = new SubresourceField(\Shopware\ShippingMethod\Writer\ShippingMethodHolidayResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Holiday\Writer\HolidayResource::class,
            \Shopware\ShippingMethod\Writer\ShippingMethodHolidayResource::class
        ];
    }
}
