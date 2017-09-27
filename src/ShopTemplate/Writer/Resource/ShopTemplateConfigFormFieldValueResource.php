<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopTemplateConfigFormFieldValueResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_TEMPLATE_CONFIG_FORM_FIELD_ID_FIELD = 'shopTemplateConfigFormFieldId';
    protected const SHOP_ID_FIELD = 'shopId';
    protected const VALUE_FIELD = 'value';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('shop_template_config_form_field_value');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_TEMPLATE_CONFIG_FORM_FIELD_ID_FIELD] = (new IntField('shop_template_config_form_field_id'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['shopTemplateConfigFormField'] = new ReferenceField('shopTemplateConfigFormFieldUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class);
        $this->fields['shopTemplateConfigFormFieldUuid'] = (new FkField('shop_template_config_form_field_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldValueWrittenEvent
    {
        $event = new \Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
