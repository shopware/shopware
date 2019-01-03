<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Home;

use Shopware\Storefront\Framework\Event\PageRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            IndexPageRequestEvent::NAME => 'transformRequest',
        ];
    }

    public function transformRequest(PageRequestEvent $event): void
    {
        $request = $event->getRequest();
        $transformed = $event->getPageRequest();
    }
}
