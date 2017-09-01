<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

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

class OrderBasketResource extends Resource
{
    protected const SESSIONID_FIELD = 'sessionID';
    protected const USERID_FIELD = 'userID';
    protected const ARTICLENAME_FIELD = 'articlename';
    protected const ARTICLEID_FIELD = 'articleID';
    protected const ORDERNUMBER_FIELD = 'ordernumber';
    protected const SHIPPINGFREE_FIELD = 'shippingfree';
    protected const QUANTITY_FIELD = 'quantity';
    protected const PRICE_FIELD = 'price';
    protected const NETPRICE_FIELD = 'netprice';
    protected const TAX_RATE_FIELD = 'taxRate';
    protected const DATUM_FIELD = 'datum';
    protected const MODUS_FIELD = 'modus';
    protected const ESDARTICLE_FIELD = 'esdarticle';
    protected const PARTNERID_FIELD = 'partnerID';
    protected const LASTVIEWPORT_FIELD = 'lastviewport';
    protected const USERAGENT_FIELD = 'useragent';
    protected const CONFIG_FIELD = 'config';
    protected const CURRENCYFACTOR_FIELD = 'currencyFactor';

    public function __construct()
    {
        parent::__construct('s_order_basket');
        
        $this->fields[self::SESSIONID_FIELD] = new StringField('sessionID');
        $this->fields[self::USERID_FIELD] = new IntField('userID');
        $this->fields[self::ARTICLENAME_FIELD] = (new StringField('articlename'))->setFlags(new Required());
        $this->fields[self::ARTICLEID_FIELD] = new IntField('articleID');
        $this->fields[self::ORDERNUMBER_FIELD] = (new StringField('ordernumber'))->setFlags(new Required());
        $this->fields[self::SHIPPINGFREE_FIELD] = new IntField('shippingfree');
        $this->fields[self::QUANTITY_FIELD] = new IntField('quantity');
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::NETPRICE_FIELD] = new FloatField('netprice');
        $this->fields[self::TAX_RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::DATUM_FIELD] = new DateField('datum');
        $this->fields[self::MODUS_FIELD] = new IntField('modus');
        $this->fields[self::ESDARTICLE_FIELD] = (new IntField('esdarticle'))->setFlags(new Required());
        $this->fields[self::PARTNERID_FIELD] = (new StringField('partnerID'))->setFlags(new Required());
        $this->fields[self::LASTVIEWPORT_FIELD] = (new StringField('lastviewport'))->setFlags(new Required());
        $this->fields[self::USERAGENT_FIELD] = (new StringField('useragent'))->setFlags(new Required());
        $this->fields[self::CONFIG_FIELD] = (new LongTextField('config'))->setFlags(new Required());
        $this->fields[self::CURRENCYFACTOR_FIELD] = (new FloatField('currencyFactor'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderBasketResource::class
        ];
    }
}
