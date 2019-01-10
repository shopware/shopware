<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ContentHome;

use Shopware\Storefront\Event\ContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContentHomePageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ContentEvents::CONTENTHOME_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ContentHomePageRequestEvent $event): void
    {
        //$contentHomePageRequest = $event->getContentHomePageRequest();
        //$contentHomePageRequest->getContentHomeRequest()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
