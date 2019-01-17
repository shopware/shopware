<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Storefront\Event\ContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContentHeaderPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ContentEvents::CONTENTHEADER_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ContentHeaderPageletRequestEvent $event): void
    {
        //$contentHeaderPageletRequest = $event->getContentHeaderPageletRequest();
    }
}
