<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class EmotionShopsWriteResource extends WriteResource
{
    protected const EMOTION_ID_FIELD = 'emotionId';
    protected const SHOP_ID_FIELD = 'shopId';

    public function __construct()
    {
        parent::__construct('s_emotion_shops');

        $this->fields[self::EMOTION_ID_FIELD] = (new IntField('emotion_id'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmotionShopsWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\EmotionShopsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\EmotionShopsWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\EmotionShopsWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\EmotionShopsWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
