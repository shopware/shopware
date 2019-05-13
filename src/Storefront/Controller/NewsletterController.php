<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionService;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Page\Newsletter\ConfirmSubscribe\NewsletterConfirmSubscribePageLoader;
use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewsletterController extends StorefrontController
{
    /**
     * @var NewsletterRegisterLoader|PageLoaderInterface
     */
    private $newsletterRegisterPageLoader;

    /**
     * @var PageLoaderInterface|NewsletterConfirmSubscribePageLoader
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

    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterSubscriptionService;

    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(
        PageLoaderInterface $newsletterRegisterPageLoader,
        PageLoaderInterface $newsletterConfirmRegisterPageLoader,
        NewsletterSubscriptionServiceInterface $newsletterService,
        RequestStack $requestStack,
        TranslatorInterface $translator
    ) {
        $this->newsletterRegisterPageLoader = $newsletterRegisterPageLoader;
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
    public function handle(Request $request, SalesChannelContext $context, RequestDataBag $requestDataBag): Response
    {
        $subscribe = $requestDataBag->get('option') === 'subscribe';
        $requestDataBag->add([
            'baseUrl' => $this->requestStack->getMasterRequest()->getSchemeAndHttpHost(),
        ]);

        try {
            if ($subscribe) {
                $this->newsletterService->subscribe($requestDataBag, $context);

                $this->addFlash('success', $this->translator->trans('newsletter.subscriptionPersistedSuccess'));
                $this->addFlash('info', $this->translator->trans('newsletter.subscriptionPersistedInfo'));
            } else {
                $this->newsletterService->unsubscribe($requestDataBag, $context);

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

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/newsletter/subscribe", name="frontend.newsletter.subscribe", methods={"GET"})
     */
    public function subscribeMail(SalesChannelContext $context, Request $request, QueryDataBag $queryDataBag): Response
    {
        try {
            $this->newsletterService->confirm($queryDataBag, $context);
        } catch (\Throwable $throwable) {
            $this->addFlash('danger', $this->translator->trans('newsletter.subscriptionConfirmationFailed'));

            throw new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    /**
     * @Route(path="/widgets/account/newsletter", name="widgets.account.newsletter", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function subscriberCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        /** @var bool $subscribed */
        $subscribed = ($request->get('option', false) === NewsletterSubscriptionService::STATUS_DIRECT);

        if (!$subscribed) {
            $dataBag->set('option', 'unsubscribe');
        }

        $messages = [];
        $success = null;

        if ($subscribed) {
            try {
                $this->newsletterSubscriptionService->subscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);

                $this->accountService->setNewsletterFlag($context->getCustomer(), true, $context);

                $success = true;
                $messages[] = ['type' => 'success', 'text' => $this->translator->trans('newsletter.subscriptionConfirmationSuccess')];
            } catch (\Exception $exception) {
                $success = false;
                $messages[] = ['type' => 'danger', 'text' => $this->translator->trans('newsletter.subscriptionConfirmationFailed')];
            }

            return $this->renderStorefront('@Storefront/page/account/newsletter.html.twig', [
                'customer' => $context->getCustomer(),
                'messages' => $messages,
                'success' => $success,
            ]);
        }

        try {
            $this->newsletterSubscriptionService->unsubscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);
            $this->accountService->setNewsletterFlag($context->getCustomer(), false, $context);

            $success = true;
            $messages[] = ['type' => 'success', 'text' => $this->translator->trans('newsletter.subscriptionRevokeSuccess')];
        } catch (\Exception $exception) {
            $success = false;
            $messages[] = ['type' => 'danger', 'text' => $this->translator->trans('error.message-default')];
        }

        return $this->renderStorefront('@Storefront/page/account/newsletter.html.twig', [
            'customer' => $context->getCustomer(),
            'messages' => $messages,
            'success' => $success,
        ]);
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());

        return $dataBag;
    }
}
