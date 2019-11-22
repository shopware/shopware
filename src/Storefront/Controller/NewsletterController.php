<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionService;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoader;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NewsletterController extends StorefrontController
{
    /**
     * @var NewsletterRegisterPageLoader
     */
    private $newsletterRegisterPageLoader;

    /**
     * @var NewsletterSubscribePageLoader
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
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        NewsletterRegisterPageLoader $newsletterRegisterPageLoader,
        NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        NewsletterSubscriptionServiceInterface $newsletterService,
        RequestStack $requestStack,
        EntityRepositoryInterface $customerRepository
    ) {
        $this->newsletterRegisterPageLoader = $newsletterRegisterPageLoader;
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->newsletterService = $newsletterService;
        $this->requestStack = $requestStack;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @Route("/newsletter", name="frontend.newsletter.register.page", methods={"GET"})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->newsletterRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/newsletter/index.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/newsletter", name="frontend.newsletter.register.handle", methods={"POST"})
     */
    public function handle(Request $request, SalesChannelContext $context, RequestDataBag $requestDataBag): Response
    {
        $subscribe = $requestDataBag->get('option') === 'subscribe';

        try {
            if ($subscribe) {
                $this->newsletterService->subscribe($requestDataBag, $context);

                $this->addFlash('success', $this->trans('newsletter.subscriptionPersistedSuccess'));
                $this->addFlash('info', $this->trans('newsletter.subscriptionPersistedInfo'));
            } else {
                $this->newsletterService->unsubscribe($requestDataBag, $context);

                $this->addFlash('success', $this->trans('newsletter.subscriptionRevokeSuccess'));
            }
        } catch (ConstraintViolationException $exception) {
            foreach ($exception->getViolations() as $violation) {
                $this->addFlash('danger', $violation->getMessage());
            }
        } catch (\Exception $exception) {
            if ($subscribe) {
                $this->addFlash('danger', $this->trans('error.message-default'));
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
            $this->addFlash('danger', $this->trans('newsletter.subscriptionConfirmationFailed'));

            throw new \Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    /**
     * @Route("/widgets/account/newsletter", name="frontend.account.newsletter", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
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
                $this->newsletterService->subscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);

                $this->setNewsletterFlag($context->getCustomer(), true, $context);

                $success = true;
                $messages[] = ['type' => 'success', 'text' => $this->trans('newsletter.subscriptionConfirmationSuccess')];
            } catch (\Exception $exception) {
                $success = false;
                $messages[] = ['type' => 'danger', 'text' => $this->trans('newsletter.subscriptionConfirmationFailed')];
            }

            return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
                'customer' => $context->getCustomer(),
                'messages' => $messages,
                'success' => $success,
            ]);
        }

        try {
            $this->newsletterService->unsubscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);
            $this->setNewsletterFlag($context->getCustomer(), false, $context);

            $success = true;
            $messages[] = ['type' => 'success', 'text' => $this->trans('newsletter.subscriptionRevokeSuccess')];
        } catch (\Exception $exception) {
            $success = false;
            $messages[] = ['type' => 'danger', 'text' => $this->trans('error.message-default')];
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
            'customer' => $context->getCustomer(),
            'messages' => $messages,
            'success' => $success,
        ]);
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());
        $dataBag->set('title', $customer->getTitle());
        $dataBag->set('firstName', $customer->getFirstName());
        $dataBag->set('lastName', $customer->getLastName());
        $dataBag->set('zipCode', $customer->getDefaultShippingAddress()->getZipCode());
        $dataBag->set('city', $customer->getDefaultShippingAddress()->getCity());

        return $dataBag;
    }

    private function setNewsletterFlag(CustomerEntity $customer, bool $newsletter, SalesChannelContext $context): void
    {
        $customer->setNewsletter($newsletter);

        $this->customerRepository->update(
            [
                ['id' => $customer->getId(), 'newsletter' => $newsletter],
            ],
            $context->getContext()
        );
    }
}
