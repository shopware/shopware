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

class OrderBasketResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_basket');
        
        $this->fields['sessionID'] = new StringField('sessionID');
        $this->fields['userID'] = new IntField('userID');
        $this->fields['articlename'] = (new StringField('articlename'))->setFlags(new Required());
        $this->fields['articleID'] = new IntField('articleID');
        $this->fields['ordernumber'] = (new StringField('ordernumber'))->setFlags(new Required());
        $this->fields['shippingfree'] = new IntField('shippingfree');
        $this->fields['quantity'] = new IntField('quantity');
        $this->fields['price'] = new FloatField('price');
        $this->fields['netprice'] = new FloatField('netprice');
        $this->fields['taxRate'] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields['datum'] = new DateField('datum');
        $this->fields['modus'] = new IntField('modus');
        $this->fields['esdarticle'] = (new IntField('esdarticle'))->setFlags(new Required());
        $this->fields['partnerID'] = (new StringField('partnerID'))->setFlags(new Required());
        $this->fields['lastviewport'] = (new StringField('lastviewport'))->setFlags(new Required());
        $this->fields['useragent'] = (new StringField('useragent'))->setFlags(new Required());
        $this->fields['config'] = (new LongTextField('config'))->setFlags(new Required());
        $this->fields['currencyFactor'] = (new FloatField('currencyFactor'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderBasketResource::class
        ];
    }
}
