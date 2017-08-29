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

class UserAddressesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_user_addresses');
        
        $this->fields['company'] = new StringField('company');
        $this->fields['department'] = new StringField('department');
        $this->fields['salutation'] = (new StringField('salutation'))->setFlags(new Required());
        $this->fields['title'] = new StringField('title');
        $this->fields['firstname'] = (new StringField('firstname'))->setFlags(new Required());
        $this->fields['lastname'] = (new StringField('lastname'))->setFlags(new Required());
        $this->fields['street'] = new StringField('street');
        $this->fields['zipcode'] = (new StringField('zipcode'))->setFlags(new Required());
        $this->fields['city'] = (new StringField('city'))->setFlags(new Required());
        $this->fields['ustid'] = new StringField('ustid');
        $this->fields['phone'] = new StringField('phone');
        $this->fields['additionalAddressLine1'] = new StringField('additional_address_line1');
        $this->fields['additionalAddressLine2'] = new StringField('additional_address_line2');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\UserAddressesResource::class
        ];
    }
}
