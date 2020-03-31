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
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
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
    private $paymentMethodPageRoute;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodPageRoute;

    public function __construct(
        HeaderPageletLoaderInterface $headerLoader,
        FooterPageletLoaderInterface $footerLoader,
        SystemConfigService $systemConfigService,
        AbstractPaymentMethodRoute $paymentMethodPageRoute,
        AbstractShippingMethodRoute $shippingMethodPageRoute
    ) {
        $this->headerLoader = $headerLoader;
        $this->footerLoader = $footerLoader;
        $this->systemConfigService = $systemConfigService;
        $this->paymentMethodPageRoute = $paymentMethodPageRoute;
        $this->shippingMethodPageRoute = $shippingMethodPageRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): Page
    {
        $page = new Page();

        if ($request->isXmlHttpRequest()) {
            return $page;
        }
        $page->setHeader(
            $this->headerLoader->load($request, $salesChannelContext)
        );

        $page->setFooter(
            $this->footerLoader->load($request, $salesChannelContext)
        );

        $page->setSalesChannelShippingMethods(
            $this->shippingMethodPageRoute->load(new Request(), $salesChannelContext)->getShippingMethods()
        );

        $page->setSalesChannelPaymentMethods(
            $this->paymentMethodPageRoute->load(new Request(), $salesChannelContext)->getPaymentMethods()
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
