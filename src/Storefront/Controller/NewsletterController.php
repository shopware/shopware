<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('after-sales')]
class NewsletterController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        private readonly AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        private readonly NewsletterAccountPageletLoader $newsletterAccountPageletLoader
    ) {
    }

    #[Route(
        path: '/newsletter-subscribe',
        name: 'frontend.newsletter.subscribe',
        methods: [Request::METHOD_GET]
    )]
    public function subscribeMail(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag): Response
    {
        if ($this->isHeadRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $this->newsletterConfirmRoute->confirm($queryDataBag->toRequestDataBag(), $context);
        } catch (NewsletterException) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            return $this->forwardToRoute('frontend.home.page');
        } catch (\Throwable $throwable) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            throw $throwable;
        }

        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'forwardTo')) {
            $this->addFlash(self::SUCCESS, $this->trans('newsletter.subscriptionCompleted'));

            return $this->createActionResponse($request);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/widgets/account/newsletter',
        name: 'frontend.account.newsletter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $pagelet = $this->newsletterAccountPageletLoader->action($request, $dataBag, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
            'newsletterAccountPagelet' => $pagelet,
        ]);
    }
}
