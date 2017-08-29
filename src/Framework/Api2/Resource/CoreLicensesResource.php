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

class CoreLicensesResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_licenses');
        
        $this->fields['module'] = (new StringField('module'))->setFlags(new Required());
        $this->fields['host'] = (new StringField('host'))->setFlags(new Required());
        $this->fields['label'] = (new StringField('label'))->setFlags(new Required());
        $this->fields['license'] = (new LongTextField('license'))->setFlags(new Required());
        $this->fields['version'] = (new StringField('version'))->setFlags(new Required());
        $this->fields['notation'] = new StringField('notation');
        $this->fields['type'] = (new IntField('type'))->setFlags(new Required());
        $this->fields['source'] = (new IntField('source'))->setFlags(new Required());
        $this->fields['added'] = (new DateField('added'))->setFlags(new Required());
        $this->fields['creation'] = new DateField('creation');
        $this->fields['expiration'] = new DateField('expiration');
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['pluginId'] = new IntField('plugin_id');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreLicensesResource::class
        ];
    }
}
