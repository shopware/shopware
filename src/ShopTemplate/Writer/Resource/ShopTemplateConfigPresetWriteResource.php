<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Event\ShopTemplateConfigPresetWrittenEvent;

class ShopTemplateConfigPresetWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SHOP_TEMPLATE_ID_FIELD = 'shopTemplateId';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const ELEMENT_VALUES_FIELD = 'elementValues';

    public function __construct()
    {
        parent::__construct('shop_template_config_preset');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SHOP_TEMPLATE_ID_FIELD] = (new IntField('shop_template_id'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::ELEMENT_VALUES_FIELD] = (new LongTextField('element_values'))->setFlags(new Required());
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopTemplateWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateConfigPresetWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShopTemplateConfigPresetWrittenEvent($uuids, $context, $rawData, $errors);

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
