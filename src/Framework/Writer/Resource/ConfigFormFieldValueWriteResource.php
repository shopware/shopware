<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ConfigFormFieldValueWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const CONFIG_FORM_FIELD_UUID_FIELD = 'configFormFieldUuid';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('config_form_field_value');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CONFIG_FORM_FIELD_UUID_FIELD] = (new StringField('config_form_field_uuid'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new LongTextField('value'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\ConfigFormFieldValueWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ConfigFormFieldValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
