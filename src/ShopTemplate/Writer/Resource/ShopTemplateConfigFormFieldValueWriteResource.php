<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShopTemplateConfigFormFieldValueWriteResource extends WriteResource
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
        $this->fields['shopTemplateConfigFormField'] = new ReferenceField('shopTemplateConfigFormFieldUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldWriteResource::class);
        $this->fields['shopTemplateConfigFormFieldUuid'] = (new FkField('shop_template_config_form_field_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldValueWrittenEvent
    {
        $event = new \Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldWriteResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
