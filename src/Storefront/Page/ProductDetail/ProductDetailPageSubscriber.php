<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Storefront\Event\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDetailPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCTDETAIL_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ProductDetailPageRequestEvent $event): void
    {
        $productDetailPageRequest = $event->getProductDetailPageRequest();
        $productDetailPageRequest->setXmlHttpRequest($event->getHttpRequest()->isXmlHttpRequest());
    }
}
