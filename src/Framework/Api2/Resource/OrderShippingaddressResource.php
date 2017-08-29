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

class OrderShippingaddressResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_order_shippingaddress');
        
        $this->fields['userID'] = new IntField('userID');
        $this->fields['orderID'] = (new IntField('orderID'))->setFlags(new Required());
        $this->fields['company'] = (new StringField('company'))->setFlags(new Required());
        $this->fields['department'] = (new StringField('department'))->setFlags(new Required());
        $this->fields['salutation'] = (new StringField('salutation'))->setFlags(new Required());
        $this->fields['firstname'] = (new StringField('firstname'))->setFlags(new Required());
        $this->fields['lastname'] = (new StringField('lastname'))->setFlags(new Required());
        $this->fields['street'] = new StringField('street');
        $this->fields['zipcode'] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields['city'] = (new StringField('city'))->setFlags(new Required());
        $this->fields['countryID'] = (new IntField('countryID'))->setFlags(new Required());
        $this->fields['stateID'] = new IntField('stateID');
        $this->fields['additionalAddressLine1'] = new StringField('additional_address_line1');
        $this->fields['additionalAddressLine2'] = new StringField('additional_address_line2');
        $this->fields['title'] = new StringField('title');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\OrderShippingaddressResource::class
        ];
    }
}
