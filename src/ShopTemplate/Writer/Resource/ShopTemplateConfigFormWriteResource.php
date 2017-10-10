<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ShopTemplate\Event\ShopTemplateConfigFormWrittenEvent;

class ShopTemplateConfigFormWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const SHOP_TEMPLATE_ID_FIELD = 'shopTemplateId';
    protected const TYPE_FIELD = 'type';
    protected const NAME_FIELD = 'name';
    protected const TITLE_FIELD = 'title';
    protected const ATTRIBUTES_FIELD = 'attributes';

    public function __construct()
    {
        parent::__construct('shop_template_config_form');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::SHOP_TEMPLATE_ID_FIELD] = (new IntField('shop_template_id'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::ATTRIBUTES_FIELD] = new LongTextField('attributes');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['fields'] = new SubresourceField(ShopTemplateConfigFormFieldWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            ShopTemplateWriteResource::class,
            ShopTemplateConfigFormFieldWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateConfigFormWrittenEvent
    {
        $event = new ShopTemplateConfigFormWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopTemplateWriteResource::class])) {
            $event->addEvent(ShopTemplateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopTemplateConfigFormFieldWriteResource::class])) {
            $event->addEvent(ShopTemplateConfigFormFieldWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
