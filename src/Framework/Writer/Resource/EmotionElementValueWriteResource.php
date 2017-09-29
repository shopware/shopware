<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmotionElementValueWriteResource extends WriteResource
{
    protected const EMOTIONID_FIELD = 'emotionID';
    protected const ELEMENTID_FIELD = 'elementID';
    protected const COMPONENTID_FIELD = 'componentID';
    protected const FIELDID_FIELD = 'fieldID';
    protected const VALUE_FIELD = 'value';

    public function __construct()
    {
        parent::__construct('s_emotion_element_value');

        $this->fields[self::EMOTIONID_FIELD] = (new IntField('emotionID'))->setFlags(new Required());
        $this->fields[self::ELEMENTID_FIELD] = (new IntField('elementID'))->setFlags(new Required());
        $this->fields[self::COMPONENTID_FIELD] = (new IntField('componentID'))->setFlags(new Required());
        $this->fields[self::FIELDID_FIELD] = (new IntField('fieldID'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = new LongTextField('value');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmotionElementValueWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\EmotionElementValueWrittenEvent
    {
        $event = new \Shopware\Framework\Event\EmotionElementValueWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\EmotionElementValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\EmotionElementValueWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
