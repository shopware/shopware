<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHome;

use Shopware\Storefront\Event\ContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContentHomePageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ContentEvents::CONTENTHOME_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ContentHomePageletRequestEvent $event): void
    {
        //$contentHomePageletRequest = $event->getContentHomePageletRequest();
    }
}
