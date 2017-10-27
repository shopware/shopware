<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\ShopTemplate\Event\ShopTemplateConfigFormFieldValueWrittenEvent;

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
        $this->fields['shopTemplateConfigFormField'] = new ReferenceField('shopTemplateConfigFormFieldUuid', 'uuid', ShopTemplateConfigFormFieldWriteResource::class);
        $this->fields['shopTemplateConfigFormFieldUuid'] = (new FkField('shop_template_config_form_field_uuid', ShopTemplateConfigFormFieldWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopTemplateConfigFormFieldWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateConfigFormFieldValueWrittenEvent
    {
        $event = new ShopTemplateConfigFormFieldValueWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
