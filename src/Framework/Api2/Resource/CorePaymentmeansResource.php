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

class CorePaymentmeansResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_paymentmeans');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['template'] = (new StringField('template'))->setFlags(new Required());
        $this->fields['class'] = (new StringField('class'))->setFlags(new Required());
        $this->fields['table'] = (new StringField('table'))->setFlags(new Required());
        $this->fields['hide'] = (new IntField('hide'))->setFlags(new Required());
        $this->fields['additionaldescription'] = (new LongTextField('additionaldescription'))->setFlags(new Required());
        $this->fields['debitPercent'] = new FloatField('debit_percent');
        $this->fields['surcharge'] = new FloatField('surcharge');
        $this->fields['surchargestring'] = (new StringField('surchargestring'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['active'] = new BoolField('active');
        $this->fields['esdactive'] = (new IntField('esdactive'))->setFlags(new Required());
        $this->fields['embediframe'] = (new StringField('embediframe'))->setFlags(new Required());
        $this->fields['hideprospect'] = (new IntField('hideprospect'))->setFlags(new Required());
        $this->fields['action'] = new StringField('action');
        $this->fields['pluginID'] = new IntField('pluginID');
        $this->fields['source'] = new IntField('source');
        $this->fields['mobileInactive'] = new IntField('mobile_inactive');
        $this->fields['riskRules'] = new LongTextField('risk_rules');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CorePaymentmeansResource::class
        ];
    }
}
