<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShoppingWorldComponentResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const X_TYPE_FIELD = 'xType';
    protected const CONVERT_FUNCTION_FIELD = 'convertFunction';
    protected const DESCRIPTION_FIELD = 'description';
    protected const TEMPLATE_FIELD = 'template';
    protected const CLS_FIELD = 'cls';
    protected const PLUGIN_ID_FIELD = 'pluginId';
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('shopping_world_component');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::X_TYPE_FIELD] = (new StringField('x_type'))->setFlags(new Required());
        $this->fields[self::CONVERT_FUNCTION_FIELD] = new StringField('convert_function');
        $this->fields[self::DESCRIPTION_FIELD] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::CLS_FIELD] = (new StringField('cls'))->setFlags(new Required());
        $this->fields[self::PLUGIN_ID_FIELD] = new IntField('plugin_id');
        $this->primaryKeyFields[self::UUID_FIELD] = new UuidField('uuid');
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', \Shopware\Framework\Write\Resource\PluginResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', \Shopware\Framework\Write\Resource\PluginResource::class, 'uuid'));
        $this->fields['fields'] = new SubresourceField(\Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\PluginResource::class,
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class,
            \Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\ShoppingWorldComponentWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ShoppingWorldComponentWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\PluginResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PluginResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ShoppingWorldComponentResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ShoppingWorldComponentFieldResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
