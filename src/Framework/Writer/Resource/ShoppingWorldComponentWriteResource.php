<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ShoppingWorldComponentWrittenEvent;

class ShoppingWorldComponentWriteResource extends WriteResource
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
        $this->fields['plugin'] = new ReferenceField('pluginUuid', 'uuid', PluginWriteResource::class);
        $this->fields['pluginUuid'] = (new FkField('plugin_uuid', PluginWriteResource::class, 'uuid'));
        $this->fields['fields'] = new SubresourceField(ShoppingWorldComponentFieldWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            PluginWriteResource::class,
            self::class,
            ShoppingWorldComponentFieldWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShoppingWorldComponentWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShoppingWorldComponentWrittenEvent($uuids, $context, $rawData, $errors);

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
