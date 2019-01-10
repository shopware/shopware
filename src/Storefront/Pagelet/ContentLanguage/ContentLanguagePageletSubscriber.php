<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentLanguage;

use Shopware\Storefront\Event\ContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContentLanguagePageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ContentEvents::CONTENTLANGUAGE_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ContentLanguagePageletRequestEvent $event): void
    {
        //$contentLanguagePageletRequest = $event->getContentLanguagePageletRequest();
    }
}
