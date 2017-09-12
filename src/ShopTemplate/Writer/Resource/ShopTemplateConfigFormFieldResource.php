<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ShopTemplateConfigFormFieldResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_TEMPLATE_ID_FIELD = 'shopTemplateId';
    protected const TYPE_FIELD = 'type';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const DEFAULT_VALUE_FIELD = 'defaultValue';
    protected const SELECTION_FIELD = 'selection';
    protected const FIELD_LABEL_FIELD = 'fieldLabel';
    protected const SUPPORT_TEXT_FIELD = 'supportText';
    protected const ALLOW_BLANK_FIELD = 'allowBlank';
    protected const SHOP_TEMPLATE_CONFIG_FORM_ID_FIELD = 'shopTemplateConfigFormId';
    protected const ATTRIBUTES_FIELD = 'attributes';
    protected const LESS_COMPATIBLE_FIELD = 'lessCompatible';

    public function __construct()
    {
        parent::__construct('shop_template_config_form_field');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_TEMPLATE_ID_FIELD] = (new IntField('shop_template_id'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::DEFAULT_VALUE_FIELD] = new LongTextField('default_value');
        $this->fields[self::SELECTION_FIELD] = new LongTextField('selection');
        $this->fields[self::FIELD_LABEL_FIELD] = new StringField('field_label');
        $this->fields[self::SUPPORT_TEXT_FIELD] = new StringField('support_text');
        $this->fields[self::ALLOW_BLANK_FIELD] = new BoolField('allow_blank');
        $this->fields[self::SHOP_TEMPLATE_CONFIG_FORM_ID_FIELD] = (new IntField('shop_template_config_form_id'))->setFlags(new Required());
        $this->fields[self::ATTRIBUTES_FIELD] = new LongTextField('attributes');
        $this->fields[self::LESS_COMPATIBLE_FIELD] = new BoolField('less_compatible');
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopTemplateConfigForm'] = new ReferenceField('shopTemplateConfigFormUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormResource::class);
        $this->fields['shopTemplateConfigFormUuid'] = (new FkField('shop_template_config_form_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['values'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class
        ];
    }
}
