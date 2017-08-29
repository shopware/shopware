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

class CoreCountriesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_countries');
        
        $this->fields['countryname'] = new StringField('countryname');
        $this->fields['countryiso'] = new StringField('countryiso');
        $this->fields['areaID'] = new IntField('areaID');
        $this->fields['countryen'] = new StringField('countryen');
        $this->fields['position'] = new IntField('position');
        $this->fields['notice'] = new LongTextField('notice');
        $this->fields['shippingfree'] = new IntField('shippingfree');
        $this->fields['taxfree'] = new IntField('taxfree');
        $this->fields['taxfreeUstid'] = new IntField('taxfree_ustid');
        $this->fields['taxfreeUstidChecked'] = new IntField('taxfree_ustid_checked');
        $this->fields['active'] = new BoolField('active');
        $this->fields['iso3'] = new StringField('iso3');
        $this->fields['displayStateInRegistration'] = (new IntField('display_state_in_registration'))->setFlags(new Required());
        $this->fields['forceStateInRegistration'] = (new IntField('force_state_in_registration'))->setFlags(new Required());
        $this->fields['userAddressess'] = new SubresourceField(\Shopware\Framework\Api2\Resource\UserAddressesResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreCountriesResource::class,
            \Shopware\Framework\Api2\Resource\UserAddressesResource::class
        ];
    }
}
