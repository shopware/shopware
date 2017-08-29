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

class AttributeConfigurationResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_attribute_configuration');
        
        $this->fields['tableName'] = (new StringField('table_name'))->setFlags(new Required());
        $this->fields['columnName'] = (new StringField('column_name'))->setFlags(new Required());
        $this->fields['columnType'] = (new StringField('column_type'))->setFlags(new Required());
        $this->fields['defaultValue'] = new StringField('default_value');
        $this->fields['position'] = (new IntField('position'))->setFlags(new Required());
        $this->fields['translatable'] = (new IntField('translatable'))->setFlags(new Required());
        $this->fields['displayInBackend'] = (new IntField('display_in_backend'))->setFlags(new Required());
        $this->fields['custom'] = (new IntField('custom'))->setFlags(new Required());
        $this->fields['helpText'] = new LongTextField('help_text');
        $this->fields['supportText'] = new StringField('support_text');
        $this->fields['label'] = new StringField('label');
        $this->fields['entity'] = new StringField('entity');
        $this->fields['arrayStore'] = new LongTextField('array_store');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\AttributeConfigurationResource::class
        ];
    }
}
