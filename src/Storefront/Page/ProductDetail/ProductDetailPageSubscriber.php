<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Storefront\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDetailPageSubscriber implements EventSubscriberInterface
{
    public const ROUTE_PARAMETER = '_route';

    public const ROUTE_PARAMS_PARAMETER = '_route_params';

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::DETAIL_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ProductDetailPageRequestEvent $event): void
    {
        $detailPageRequest = $event->getDetailPageRequest();
        $detailPageRequest->setxmlHttpRequest($event->getRequest()->isXmlHttpRequest());
    }
}
