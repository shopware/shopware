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

class LibraryComponentFieldResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_library_component_field');
        
        $this->fields['componentID'] = (new IntField('componentID'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['xType'] = (new StringField('x_type'))->setFlags(new Required());
        $this->fields['valueType'] = (new StringField('value_type'))->setFlags(new Required());
        $this->fields['fieldLabel'] = (new StringField('field_label'))->setFlags(new Required());
        $this->fields['supportText'] = (new StringField('support_text'))->setFlags(new Required());
        $this->fields['helpTitle'] = (new StringField('help_title'))->setFlags(new Required());
        $this->fields['helpText'] = (new LongTextField('help_text'))->setFlags(new Required());
        $this->fields['store'] = (new StringField('store'))->setFlags(new Required());
        $this->fields['displayField'] = (new StringField('display_field'))->setFlags(new Required());
        $this->fields['valueField'] = (new StringField('value_field'))->setFlags(new Required());
        $this->fields['defaultValue'] = (new StringField('default_value'))->setFlags(new Required());
        $this->fields['allowBlank'] = (new IntField('allow_blank'))->setFlags(new Required());
        $this->fields['translatable'] = new IntField('translatable');
        $this->fields['position'] = new IntField('position');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\LibraryComponentFieldResource::class
        ];
    }
}
