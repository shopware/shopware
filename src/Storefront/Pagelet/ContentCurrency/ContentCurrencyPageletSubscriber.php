<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentCurrency;

use Shopware\Storefront\Event\ContentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ContentCurrencyPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ContentEvents::CONTENTCURRENCY_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ContentCurrencyPageletRequestEvent $event): void
    {
        //$contentCurrencyPageletRequest = $event->getContentCurrencyPageletRequest();
    }
}
