<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class GenericPageLoader implements GenericPageLoaderInterface
{
    /**
     * @var HeaderPageletLoaderInterface
     */
    private $headerLoader;

    /**
     * @var FooterPageletLoaderInterface
     */
    private $footerLoader;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $paymentMethodRoute;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        HeaderPageletLoaderInterface $headerLoader,
        FooterPageletLoaderInterface $footerLoader,
        SystemConfigService $systemConfigService,
        AbstractPaymentMethodRoute $paymentMethodRoute,
        AbstractShippingMethodRoute $shippingMethodRoute,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->headerLoader = $headerLoader;
        $this->footerLoader = $footerLoader;
        $this->systemConfigService = $systemConfigService;
        $this->paymentMethodRoute = $paymentMethodRoute;
        $this->shippingMethodRoute = $shippingMethodRoute;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $context): Page
    {
        $page = new Page();

        if ($request->isXmlHttpRequest()) {
            return $page;
        }
        $page->setHeader(
            $this->headerLoader->load($request, $context)
        );

        $page->setFooter(
            $this->footerLoader->load($request, $context)
        );

        $shippingMethodRouteRequestEvent = new ShippingMethodRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($shippingMethodRouteRequestEvent);
        $page->setSalesChannelShippingMethods(
            $this->shippingMethodRoute->load($shippingMethodRouteRequestEvent->getStoreApiRequest(), $context)->getShippingMethods()
        );

        $paymentMethodRouteRequestEvent = new PaymentMethodRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($paymentMethodRouteRequestEvent);
        $page->setSalesChannelPaymentMethods(
            $this->paymentMethodRoute->load($paymentMethodRouteRequestEvent->getStoreApiRequest(), $context)->getPaymentMethods()
        );

        $page->setMetaInformation((new MetaInformation())->assign([
            'revisit' => '15 days',
            'robots' => 'index,follow',
            'xmlLang' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE) ?? '',
            'metaTitle' => $this->systemConfigService->get('core.basicInformation.shopName') ?? '',
        ]));

        return $page;
    }
}
