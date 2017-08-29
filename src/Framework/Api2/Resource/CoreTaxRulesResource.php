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

class CoreTaxRulesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_tax_rules');
        
        $this->fields['areaID'] = new IntField('areaID');
        $this->fields['countryID'] = new IntField('countryID');
        $this->fields['stateID'] = new IntField('stateID');
        $this->fields['groupID'] = (new IntField('groupID'))->setFlags(new Required());
        $this->fields['customerGroupID'] = (new IntField('customer_groupID'))->setFlags(new Required());
        $this->fields['tax'] = (new FloatField('tax'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreTaxRulesResource::class
        ];
    }
}
