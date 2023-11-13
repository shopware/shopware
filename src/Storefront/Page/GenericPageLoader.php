<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class GenericPageLoader implements GenericPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HeaderPageletLoaderInterface $headerLoader,
        private readonly FooterPageletLoaderInterface $footerLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): Page
    {
        return Profiler::trace('generic-page-loader', function () use ($request, $context) {
            $page = new Page();

            if ($request->isXmlHttpRequest()) {
                $this->eventDispatcher->dispatch(
                    new GenericPageLoadedEvent($page, $context, $request)
                );

                return $page;
            }
            $page->setHeader(
                $this->headerLoader->load($request, $context)
            );

            $page->setFooter(
                $this->footerLoader->load($request, $context)
            );

            $criteria = new Criteria();
            $criteria->setTitle('generic-page::shipping-methods');

            $event = new ShippingMethodRouteRequestEvent($request, new Request(), $context, $criteria);
            $this->eventDispatcher->dispatch($event);

            $shippingMethods = $this->shippingMethodRoute
                ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
                ->getShippingMethods();

            $page->setSalesChannelShippingMethods($shippingMethods);

            $criteria = new Criteria();
            $criteria->setTitle('generic-page::payment-methods');

            $event = new PaymentMethodRouteRequestEvent($request, new Request(), $context, $criteria);
            $this->eventDispatcher->dispatch($event);

            $paymentMethods = $this->paymentMethodRoute
                ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
                ->getPaymentMethods();

            $page->setSalesChannelPaymentMethods($paymentMethods);

            $page->setMetaInformation((new MetaInformation())->assign([
                'revisit' => '15 days',
                'robots' => 'index,follow',
                'xmlLang' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE) ?? '',
                'metaTitle' => $this->systemConfigService->getString('core.basicInformation.shopName', $context->getSalesChannel()->getId()),
            ]));

            $this->eventDispatcher->dispatch(
                new GenericPageLoadedEvent($page, $context, $request)
            );

            return $page;
        });
    }
}
