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

class CoreCountriesStatesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_countries_states');
        
        $this->fields['countryID'] = new IntField('countryID');
        $this->fields['name'] = new StringField('name');
        $this->fields['shortcode'] = (new StringField('shortcode'))->setFlags(new Required());
        $this->fields['position'] = new IntField('position');
        $this->fields['active'] = new BoolField('active');
        $this->fields['userAddressess'] = new SubresourceField(\Shopware\Framework\Api2\Resource\UserAddressesResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreCountriesStatesResource::class,
            \Shopware\Framework\Api2\Resource\UserAddressesResource::class
        ];
    }
}
