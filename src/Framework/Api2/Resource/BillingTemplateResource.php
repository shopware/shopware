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

class BillingTemplateResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_billing_template');
        
        $this->primaryKeyFields['iD'] = (new IntField('ID'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['value'] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['typ'] = (new IntField('typ'))->setFlags(new Required());
        $this->fields['group'] = (new StringField('group'))->setFlags(new Required());
        $this->fields['desc'] = (new StringField('desc'))->setFlags(new Required());
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['show'] = new IntField('show');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\BillingTemplateResource::class
        ];
    }
}
