<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Framework\Writer\Resource\PluginWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;
use Shopware\ShopTemplate\Event\ShopTemplateWrittenEvent;

class ShopTemplateWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TEMPLATE_FIELD = 'template';
    protected const NAME_FIELD = 'name';
    protected const DESCRIPTION_FIELD = 'description';
    protected const AUTHOR_FIELD = 'author';
    protected const LICENSE_FIELD = 'license';
    protected const ESI_FIELD = 'esi';
    protected const STYLE_SUPPORT_FIELD = 'styleSupport';
    protected const VERSION_FIELD = 'version';
    protected const EMOTION_FIELD = 'emotion';
    protected const PLUGIN_ID_FIELD = 'pluginId';
    protected const PARENT_ID_FIELD = 'parentId';

    public function __construct()
    {
        parent::__construct('shop_template');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new StringField('description');
        $this->fields[self::AUTHOR_FIELD] = new StringField('author');
        $this->fields[self::LICENSE_FIELD] = new StringField('license');
        $this->fields[self::ESI_FIELD] = new BoolField('esi');
        $this->fields[self::STYLE_SUPPORT_FIELD] = new BoolField('style_support');
        $this->fields[self::VERSION_FIELD] = new IntField('version');
        $this->fields[self::EMOTION_FIELD] = (new BoolField('emotion'))->setFlags(new Required());
        $this->fields[self::PLUGIN_ID_FIELD] = new IntField('plugin_id');
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', PluginWriteResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', PluginWriteResource::class, 'uuid'));
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['configForms'] = new SubresourceField(ShopTemplateConfigFormWriteResource::class);
        $this->fields['configFormFields'] = new SubresourceField(ShopTemplateConfigFormFieldWriteResource::class);
        $this->fields['configPresets'] = new SubresourceField(ShopTemplateConfigPresetWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ShopWriteResource::class,
            PluginWriteResource::class,
            self::class,
            ShopTemplateConfigFormWriteResource::class,
            ShopTemplateConfigFormFieldWriteResource::class,
            ShopTemplateConfigPresetWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateWrittenEvent
    {
        $event = new ShopTemplateWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[PluginWriteResource::class])) {
            $event->addEvent(PluginWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopTemplateConfigFormWriteResource::class])) {
            $event->addEvent(ShopTemplateConfigFormWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopTemplateConfigFormFieldWriteResource::class])) {
            $event->addEvent(ShopTemplateConfigFormFieldWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopTemplateConfigPresetWriteResource::class])) {
            $event->addEvent(ShopTemplateConfigPresetWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
