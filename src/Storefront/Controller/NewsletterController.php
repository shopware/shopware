<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('buyers-experience')]
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

    #[Route(path: '/newsletter-subscribe', name: 'frontend.newsletter.subscribe', methods: ['GET'])]
    public function subscribeMail(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag): Response
    {
        /*
         * Because some email-clients try to fetch previews for links in mails, they send a HEAD-request.
         * But because Symfony is routing HEAD-requests as GET-requests, a subscriber would be confirmed without
         * clicking the link, only by the HEAD-request.
         * Beware: $request->getMethod() or $request->getRealMethod() will both return "GET"
         */
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $this->newsletterConfirmRoute->confirm($queryDataBag->toRequestDataBag(), $context);
        } catch (NewsletterException) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            return $this->forwardToRoute('frontend.home.page');
        } catch (\Throwable $throwable) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            throw new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    #[Route(path: '/widgets/account/newsletter', name: 'frontend.account.newsletter', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $pagelet = $this->newsletterAccountPageletLoader->action($request, $dataBag, $context, $customer);

        return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
            'newsletterAccountPagelet' => $pagelet,
        ]);
    }
}
