<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
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
     * @var NewsletterSubscribePageLoader
     */
    private $newsletterConfirmRegisterPageLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AbstractNewsletterSubscribeRoute
     */
    private $newsletterSubscribeRoute;

    /**
     * @var AbstractNewsletterConfirmRoute
     */
    private $newsletterConfirmRoute;

    /**
     * @var AbstractNewsletterUnsubscribeRoute
     */
    private $newsletterUnsubscribeRoute;

    public function __construct(
        NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        EntityRepositoryInterface $customerRepository,
        AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        AbstractNewsletterUnsubscribeRoute $newsletterUnsubscribeRoute
    ) {
        $this->newsletterConfirmRegisterPageLoader = $newsletterConfirmRegisterPageLoader;
        $this->customerRepository = $customerRepository;
        $this->newsletterSubscribeRoute = $newsletterSubscribeRoute;
        $this->newsletterConfirmRoute = $newsletterConfirmRoute;
        $this->newsletterUnsubscribeRoute = $newsletterUnsubscribeRoute;
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
     * @LoginRequired()
     * @Route("/widgets/account/newsletter", name="frontend.account.newsletter", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function subscribeCustomer(Request $request, RequestDataBag $dataBag, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $subscribed = $request->get('option', false) === 'direct';

        if (!$subscribed) {
            $dataBag->set('option', 'unsubscribe');
        }

        $dataBag->set('storefrontUrl', $request->attributes->get(RequestTransformer::STOREFRONT_URL));

        $messages = [];

        if ($subscribed) {
            try {
                $this->newsletterSubscribeRoute->subscribe(
                    $this->hydrateFromCustomer($dataBag, $customer),
                    $context,
                    false
                );

                $this->setNewsletterFlag($customer, true, $context);

                $success = true;
                $messages[] = ['type' => 'success', 'text' => $this->trans('newsletter.subscriptionConfirmationSuccess')];
            } catch (\Exception $exception) {
                $success = false;
                $messages[] = ['type' => 'danger', 'text' => $this->trans('newsletter.subscriptionConfirmationFailed')];
            }

            return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
                'customer' => $customer,
                'messages' => $messages,
                'success' => $success,
            ]);
        }

        try {
            $this->newsletterUnsubscribeRoute->unsubscribe(
                $this->hydrateFromCustomer($dataBag, $customer),
                $context
            );
            $this->setNewsletterFlag($customer, false, $context);

            $success = true;
            $messages[] = ['type' => 'success', 'text' => $this->trans('newsletter.subscriptionRevokeSuccess')];
        } catch (\Exception $exception) {
            $success = false;
            $messages[] = ['type' => 'danger', 'text' => $this->trans('error.message-default')];
        }

        return $this->renderStorefront('@Storefront/storefront/page/account/newsletter.html.twig', [
            'customer' => $customer,
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
