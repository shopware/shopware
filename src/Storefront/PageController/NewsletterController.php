<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Home\HomePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsletterController extends StorefrontController
{
    /**
     * @var HomePageLoader|PageLoaderInterface
     */
    private $newsletterRegisterPageLoader;

    /**
     * @var PageLoaderInterface
     */
    private $newsletterConfirmRegisterPageLoader;

    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        PageLoaderInterface $newsletterIndexPageLoader,
        PageLoaderInterface $newsletterConfirmRegisterPageLoader,
        NewsletterSubscriptionServiceInterface $newsletterService,
        RequestStack $requestStack,
        TranslatorInterface $translator
    ) {
        $this->newsletterRegisterPageLoader = $newsletterIndexPageLoader;
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->newsletterService = $newsletterService;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
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
     * @Route("/newsletter", name="frontend.newsletter.register.handle", methods={"POST"})
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

                $this->addFlash('success', $this->translator->trans('newsletter.subscriptionPersistedSuccess'));
                $this->addFlash('info', $this->translator->trans('newsletter.subscriptionPersistedInfo'));
            } else {
                $this->newsletterService->unsubscribe($requestDataBag, $context->getContext());

                $this->addFlash('success', $this->translator->trans('newsletter.subscriptionRevokeSuccess'));
            }
        } catch (ConstraintViolationException $exception) {
            foreach ($exception->getViolations() as $violation) {
                $this->addFlash('danger', $violation->getMessage());
            }
        } catch (\Exception $exception) {
            if ($subscribe) {
                $this->addFlash('danger', $this->translator->trans('error.message-default'));
            }
        }

        return $this->forward('Shopware\Storefront\PageController\NewsletterController::index');
    }

    /**
     * @Route("/newsletter/subscribe", name="frontend.newsletter.subscribe", methods={"GET"})
     */
    public function confirmSubscribe(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag)
    {
        try {
            $this->newsletterService->confirm($queryDataBag, $context->getContext());
        } catch (\Throwable $throwable) {
            $this->addFlash('danger', $this->translator->trans('newsletter.subscriptionConfirmationFailed'));

            throw new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }
}
