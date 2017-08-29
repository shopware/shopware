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

class OrderDetailsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_details');
        
        $this->fields['orderID'] = new IntField('orderID');
        $this->fields['ordernumber'] = (new StringField('ordernumber'))->setFlags(new Required());
        $this->fields['articleID'] = new IntField('articleID');
        $this->fields['articleordernumber'] = (new StringField('articleordernumber'))->setFlags(new Required());
        $this->fields['price'] = new FloatField('price');
        $this->fields['quantity'] = new IntField('quantity');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['status'] = new IntField('status');
        $this->fields['shipped'] = new IntField('shipped');
        $this->fields['shippedgroup'] = new IntField('shippedgroup');
        $this->fields['releasedate'] = new DateField('releasedate');
        $this->fields['modus'] = (new IntField('modus'))->setFlags(new Required());
        $this->fields['esdarticle'] = (new IntField('esdarticle'))->setFlags(new Required());
        $this->fields['taxID'] = new IntField('taxID');
        $this->fields['taxRate'] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields['config'] = (new LongTextField('config'))->setFlags(new Required());
        $this->fields['ean'] = new StringField('ean');
        $this->fields['unit'] = new StringField('unit');
        $this->fields['packUnit'] = new StringField('pack_unit');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderDetailsResource::class
        ];
    }
}
