<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
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
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShopTemplateWrittenEvent($uuids, $context, $rawData, $errors);

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
