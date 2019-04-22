<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Home\HomePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewsletterController extends StorefrontController
{
    /**
     * @var HomePageLoader|PageLoaderInterface
     */
    private $newsletterRegisterPageLoader;

    /**
     * @var PageLoaderInterface
     */
    private $newsletterConfirmPageLoader;

    /**
     * @var PageLoaderInterface
     */
    private $newsletterConfirmRegisterPageLoader;

    /**
     * @var PageLoaderInterface
     */
    private $newsletterErrorPageLoader;

    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        PageLoaderInterface $newsletterIndexPageLoader,
        PageLoaderInterface $newsletterConfirmPageLoader,
        PageLoaderInterface $newsletterConfirmRegisterPageLoader,
        PageLoaderInterface $newsletterErrorPageLoader,
        NewsletterSubscriptionServiceInterface $newsletterService,
        RequestStack $requestStack
    ) {
        $this->newsletterRegisterPageLoader = $newsletterIndexPageLoader;
        $this->newsletterConfirmPageLoader = $newsletterConfirmPageLoader;
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->newsletterErrorPageLoader = $newsletterErrorPageLoader;
        $this->newsletterService = $newsletterService;
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/newsletter", name="frontend.newsletter.register.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->newsletterRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/newsletter/handle", name="frontend.newsletter.register.handle", methods={"POST"})
     */
    public function handle(SalesChannelContext $context, RequestDataBag $requestDataBag): Response
    {
        $subscribe = $requestDataBag->get('option') === 'subscribe';
        $requestDataBag->add([
            'baseUrl' => $this->requestStack->getMasterRequest()->getSchemeAndHttpHost(),
        ]);

        try {
            if ($subscribe) {
                $this->newsletterService->subscribe($requestDataBag, $context->getContext());
            } else {
                $this->newsletterService->unsubscribe($requestDataBag, $context->getContext());
            }
        } catch (\Exception $exception) {
            if ($subscribe) {
                return $this->forward('Shopware\Storefront\PageController\NewsletterController::index', ['errors' => $exception]);
            }
        }

        return $this->redirectToRoute('frontend.newsletter.register.confirm', ['subscribe' => $subscribe]);
    }

    /**
     * @Route("/newsletter/confirm", name="frontend.newsletter.register.confirm", methods={"GET"})
     */
    public function confirm(SalesChannelContext $context, Request $request)
    {
        $page = $this->newsletterConfirmPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/confirm.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/newsletter/subscribe", name="frontend.newsletter.subscribe", methods={"GET"})
     */
    public function confirmSubscribe(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag)
    {
        try {
            $this->newsletterService->confirm($queryDataBag, $context->getContext());
        } catch (\Exception $exception) {
            return $this->forward('Shopware\Storefront\PageController\NewsletterController::error', ['errors' => $exception]);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/newsletter/error", name="frontend.newsletter.error", methods={"GET"})
     */
    public function error(SalesChannelContext $context, Request $request)
    {
        $page = $this->newsletterErrorPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/error.html.twig', ['page' => $page]);
    }
}
