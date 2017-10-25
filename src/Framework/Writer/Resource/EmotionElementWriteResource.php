<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmotionElementWrittenEvent;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmotionElementWriteResource extends WriteResource
{
    protected const EMOTIONID_FIELD = 'emotionID';
    protected const COMPONENTID_FIELD = 'componentID';
    protected const START_ROW_FIELD = 'startRow';
    protected const START_COL_FIELD = 'startCol';
    protected const END_ROW_FIELD = 'endRow';
    protected const END_COL_FIELD = 'endCol';
    protected const CSS_CLASS_FIELD = 'cssClass';

    public function __construct()
    {
        parent::__construct('s_emotion_element');

        $this->fields[self::EMOTIONID_FIELD] = (new IntField('emotionID'))->setFlags(new Required());
        $this->fields[self::COMPONENTID_FIELD] = (new IntField('componentID'))->setFlags(new Required());
        $this->fields[self::START_ROW_FIELD] = (new IntField('start_row'))->setFlags(new Required());
        $this->fields[self::START_COL_FIELD] = (new IntField('start_col'))->setFlags(new Required());
        $this->fields[self::END_ROW_FIELD] = (new IntField('end_row'))->setFlags(new Required());
        $this->fields[self::END_COL_FIELD] = (new IntField('end_col'))->setFlags(new Required());
        $this->fields[self::CSS_CLASS_FIELD] = new StringField('css_class');
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmotionElementWrittenEvent
    {
        $event = new EmotionElementWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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
