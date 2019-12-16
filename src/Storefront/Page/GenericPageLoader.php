<?php declare(strict_types=1);

namespace Shopware\Storefront\Page;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\HttpFoundation\Request;

class GenericPageLoader
{
    /**
     * @var HeaderPageletLoader
     */
    private $headerLoader;

    /**
     * @var FooterPageletLoader
     */
    private $footerLoader;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $shippingMethodsRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodsRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        HeaderPageletLoader $headerLoader,
        FooterPageletLoader $footerLoader,
        SalesChannelRepositoryInterface $shippingMethodsRepository,
        SalesChannelRepositoryInterface $paymentMethodsRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->headerLoader = $headerLoader;
        $this->footerLoader = $footerLoader;
        $this->shippingMethodsRepository = $shippingMethodsRepository;
        $this->paymentMethodsRepository = $paymentMethodsRepository;
        $this->systemConfigService = $systemConfigService;
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

        $shippingMethodsCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodsRepository->search($shippingMethodsCriteria, $salesChannelContext)->getEntities();
        $page->setSalesChannelShippingMethods($shippingMethods);

        $paymentMethodsCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodsRepository->search($paymentMethodsCriteria, $salesChannelContext)->getEntities();
        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        $page->setSalesChannelPaymentMethods($paymentMethods);
        $page->setMetaInformation((new MetaInformation())->assign([
            'revisit' => '15 days',
            'robots' => 'index,follow',
            'xmlLang' => $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE),
            'metaTitle' => $this->systemConfigService->get('core.basicInformation.shopName'),
        ]));

        return $page;
    }
}
