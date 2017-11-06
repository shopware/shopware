<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\EmotionElementValueWrittenEvent;

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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): EmotionElementValueWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new EmotionElementValueWrittenEvent($uuids, $context, $rawData, $errors);

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
