<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreWidgetsResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const LABEL_FIELD = 'label';
    protected const PLUGIN_ID_FIELD = 'pluginId';

    public function __construct()
    {
        parent::__construct('s_core_widgets');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = new StringField('label');
        $this->fields[self::PLUGIN_ID_FIELD] = new IntField('plugin_id');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreWidgetsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CoreWidgetsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreWidgetsWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreWidgetsResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreWidgetsResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
