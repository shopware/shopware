<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer;

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

class ShopTemplateConfigFormFieldValueResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_TEMPLATE_CONFIG_FORM_FIELD_ID_FIELD = 'shopTemplateConfigFormFieldId';
    protected const SHOP_ID_FIELD = 'shopId';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('shop_template_config_form_field_value');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_TEMPLATE_CONFIG_FORM_FIELD_ID_FIELD] = (new IntField('shop_template_config_form_field_id'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['shopTemplateConfigFormField'] = new ReferenceField('shopTemplateConfigFormFieldUuid', 'uuid', \Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldResource::class);
        $this->fields['shopTemplateConfigFormFieldUuid'] = (new FkField('shop_template_config_form_field_uuid', \Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\ShopResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldResource::class,
            \Shopware\Shop\Writer\ShopResource::class,
            \Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldValueResource::class
        ];
    }
}
