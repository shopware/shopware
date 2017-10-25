<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ConfigFormFieldValueWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

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
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'));
    }

    public function getWriteOrder(): array
    {
        return [
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ConfigFormFieldValueWrittenEvent
    {
        $event = new ConfigFormFieldValueWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
