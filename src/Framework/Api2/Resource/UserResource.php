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

class UserResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_user');
        
        $this->fields['password'] = (new StringField('password'))->setFlags(new Required());
        $this->fields['encoder'] = new StringField('encoder');
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['active'] = new BoolField('active');
        $this->fields['accountmode'] = (new IntField('accountmode'))->setFlags(new Required());
        $this->fields['confirmationkey'] = (new StringField('confirmationkey'))->setFlags(new Required());
        $this->fields['paymentID'] = new IntField('paymentID');
        $this->fields['firstlogin'] = new DateField('firstlogin');
        $this->fields['lastlogin'] = new DateField('lastlogin');
        $this->fields['sessionID'] = new StringField('sessionID');
        $this->fields['newsletter'] = new IntField('newsletter');
        $this->fields['validation'] = new StringField('validation');
        $this->fields['affiliate'] = new IntField('affiliate');
        $this->fields['customergroup'] = (new StringField('customergroup'))->setFlags(new Required());
        $this->fields['paymentpreset'] = (new IntField('paymentpreset'))->setFlags(new Required());
        $this->fields['language'] = (new StringField('language'))->setFlags(new Required());
        $this->fields['subshopID'] = (new IntField('subshopID'))->setFlags(new Required());
        $this->fields['referer'] = (new StringField('referer'))->setFlags(new Required());
        $this->fields['pricegroupID'] = new IntField('pricegroupID');
        $this->fields['internalcomment'] = (new LongTextField('internalcomment'))->setFlags(new Required());
        $this->fields['failedlogins'] = (new IntField('failedlogins'))->setFlags(new Required());
        $this->fields['lockeduntil'] = new DateField('lockeduntil');
        $this->fields['defaultBillingAddressId'] = new IntField('default_billing_address_id');
        $this->fields['defaultShippingAddressId'] = new IntField('default_shipping_address_id');
        $this->fields['title'] = new StringField('title');
        $this->fields['salutation'] = new StringField('salutation');
        $this->fields['firstname'] = new StringField('firstname');
        $this->fields['lastname'] = new StringField('lastname');
        $this->fields['birthday'] = new DateField('birthday');
        $this->fields['customernumber'] = new StringField('customernumber');
        $this->fields['addressess'] = new SubresourceField(\Shopware\Framework\Api2\Resource\UserAddressesResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\UserResource::class,
            \Shopware\Framework\Api2\Resource\UserAddressesResource::class
        ];
    }
}
