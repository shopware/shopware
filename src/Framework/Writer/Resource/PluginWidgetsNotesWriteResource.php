<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\PluginWidgetsNotesWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class PluginWidgetsNotesWriteResource extends WriteResource
{
    protected const USERID_FIELD = 'userID';
    protected const NOTES_FIELD = 'notes';

    public function __construct()
    {
        parent::__construct('s_plugin_widgets_notes');

        $this->fields[self::USERID_FIELD] = (new IntField('userID'))->setFlags(new Required());
        $this->fields[self::NOTES_FIELD] = (new LongTextField('notes'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PluginWidgetsNotesWrittenEvent
    {
        $event = new PluginWidgetsNotesWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
