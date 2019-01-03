<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Storefront\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDetailPageletSubscriber implements EventSubscriberInterface
{
    public const GROUP_PARAMETER = 'group';

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::DETAIL_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ProductDetailPageletRequestEvent $event): void
    {
        $detailPageletRequest = $event->getDetailPageletRequest();
        $detailPageletRequest->setGroup($event->getRequest()->get(self::GROUP_PARAMETER, []));
    }
}
