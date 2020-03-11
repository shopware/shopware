<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionService;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha;
use Shopware\Storefront\Page\Newsletter\Register\NewsletterRegisterPageLoader;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Symfony\Component\HttpFoundation\Request;
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
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        NewsletterRegisterPageLoader $newsletterRegisterPageLoader,
        NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        NewsletterSubscriptionServiceInterface $newsletterService,
        EntityRepositoryInterface $customerRepository
    ) {
        $this->newsletterRegisterPageLoader = $newsletterRegisterPageLoader;
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->newsletterService = $newsletterService;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @Route("/newsletter-subscribe", name="frontend.newsletter.subscribe", methods={"GET"})
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
     * @Captcha
     */
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $subscribed = ($request->get('option', false) === NewsletterSubscriptionService::STATUS_DIRECT);

        if (!$subscribed) {
            $dataBag->set('option', 'unsubscribe');
        }

        $dataBag->set('storefrontUrl', $request->attributes->get('sw-sales-channel-absolute-base-url'));

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
