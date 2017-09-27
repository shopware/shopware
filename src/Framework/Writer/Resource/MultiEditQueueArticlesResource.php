<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Resource;

class MultiEditQueueArticlesResource extends Resource
{
    public function __construct()
    {
        parent::__construct('s_multi_edit_queue_articles');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MultiEditQueueArticlesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\MultiEditQueueArticlesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MultiEditQueueArticlesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MultiEditQueueArticlesResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MultiEditQueueArticlesResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
