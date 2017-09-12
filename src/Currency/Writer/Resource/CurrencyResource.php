<?php declare(strict_types=1);

namespace Shopware\Currency\Writer\Resource;

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

class CurrencyResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CURRENCY_FIELD = 'currency';
    protected const NAME_FIELD = 'name';
    protected const STANDARD_FIELD = 'standard';
    protected const FACTOR_FIELD = 'factor';
    protected const TEMPLATE_CHAR_FIELD = 'templateChar';
    protected const SYMBOL_POSITION_FIELD = 'symbolPosition';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('currency');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CURRENCY_FIELD] = (new StringField('currency'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::STANDARD_FIELD] = (new BoolField('standard'))->setFlags(new Required());
        $this->fields[self::FACTOR_FIELD] = (new FloatField('factor'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_CHAR_FIELD] = (new StringField('template_char'))->setFlags(new Required());
        $this->fields[self::SYMBOL_POSITION_FIELD] = (new IntField('symbol_position'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopCurrencys'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopCurrencyResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Currency\Writer\Resource\CurrencyResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopCurrencyResource::class
        ];
    }
}
