<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\ShoppingWorldComponentWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

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
        $event = new ShoppingWorldComponentWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
