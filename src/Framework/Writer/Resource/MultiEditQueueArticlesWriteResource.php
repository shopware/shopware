<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\WriteResource;

class MultiEditQueueArticlesWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('s_multi_edit_queue_articles');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\MultiEditQueueArticlesWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\MultiEditQueueArticlesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\MultiEditQueueArticlesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\MultiEditQueueArticlesWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MultiEditQueueArticlesWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
