<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\CoreWidgetsWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreWidgetsWriteResource extends WriteResource
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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CoreWidgetsWrittenEvent
    {
        $event = new CoreWidgetsWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
