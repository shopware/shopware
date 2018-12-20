<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Subscriber;

use Shopware\Storefront\Framework\Event\PageRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageSubscriber implements EventSubscriberInterface
{
    public const PREFIX = 'page';

    public static function getSubscribedEvents(): array
    {
        return [
            PageRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(PageRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getPageRequest();

        $transformed->setHttpRequest($request);
    }
}
