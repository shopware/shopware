<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class EmotionElementResource extends Resource
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
            \Shopware\Framework\Write\Resource\EmotionElementResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\Framework\Event\EmotionElementWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\Framework\Event\EmotionElementWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\Framework\Write\Resource\EmotionElementResource::createWrittenEvent($updates, $context));

        return $event;
    }
}
