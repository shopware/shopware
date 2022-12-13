<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @internal
 */
class NewsletterController extends StorefrontController
{
    private NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader;

    private AbstractNewsletterConfirmRoute $newsletterConfirmRoute;

    private NewsletterAccountPageletLoader $newsletterAccountPageletLoader;

    /**
     * @internal
     */
    public function __construct(
        NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        NewsletterAccountPageletLoader $newsletterAccountPageletLoader
    ) {
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->newsletterConfirmRoute = $newsletterConfirmRoute;
        $this->newsletterAccountPageletLoader = $newsletterAccountPageletLoader;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/newsletter-subscribe", name="frontend.newsletter.subscribe", methods={"GET"})
     */
    public function subscribeMail(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag): Response
    {
        try {
            $this->newsletterConfirmRoute->confirm($queryDataBag->toRequestDataBag(), $context);
        } catch (\Throwable $throwable) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            throw new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/widgets/account/newsletter", name="frontend.account.newsletter", methods={"POST"}, defaults={"XmlHttpRequest"=true, "_loginRequired"=true})
     */
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $pagelet = $this->newsletterAccountPageletLoader->action($request, $dataBag, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
            'newsletterAccountPagelet' => $pagelet,
        ]);
    }
}
