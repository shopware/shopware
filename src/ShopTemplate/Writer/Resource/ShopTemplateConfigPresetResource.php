<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopTemplateConfigPresetResource extends Resource
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
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigPresetResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\ShopTemplate\Event\ShopTemplateConfigPresetWrittenEvent
    {
        $event = new \Shopware\ShopTemplate\Event\ShopTemplateConfigPresetWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigPresetResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigPresetResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
