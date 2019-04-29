<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @copyright 2019 dasistweb GmbH (https://www.dasistweb.de)
 */
class AccountPageletController extends StorefrontController
{
    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterSubscriptionService;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(NewsletterSubscriptionServiceInterface $newsletterSubscriptionService, EntityRepositoryInterface $customerRepository)
    {
        $this->newsletterSubscriptionService = $newsletterSubscriptionService;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @Route(path="/widgets/account/newsletter", name="widgets.account.newsletter", methods={"POST"})
     */
    public function newsletter(Request $request, RequestDataBag $dataBag, SalesChannelContext $context): Response
    {
        //$this->denyAccessUnlessLoggedIn();

        /** @var bool $subscribed */
        $subscribed = (bool) $request->get('subscribed', false);

        $dataBag->set('option', 'unsubscribe');
        if ($subscribed) {
            $dataBag->set('option', 'subscribe');
        }

        //if subscribe
        if ($subscribed) {
            try {
                $dataBag->set('status', NewsletterSubscriptionServiceInterface::STATUS_DIRECT);
                $this->newsletterSubscriptionService->subscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);

                $this->setNewsletterFlag($context->getCustomer(), true, $context);

                $this->addFlash('success', 'subscribed! hell yeah!');
            } catch (\Exception $exception) {
                $this->addFlash('danger', 'not subscribed! hell no!');
            }
        } else {
            try {
                $this->newsletterSubscriptionService->unsubscribe($this->hydrateFromCustomer($dataBag, $context->getCustomer()), $context);
                $this->setNewsletterFlag($context->getCustomer(), false, $context);

                $this->addFlash('success', 'unsubscribed! hell no!');
            } catch (\Exception $exception) {
                $this->addFlash('danger', 'not unsubscribed! hell yeah!');
            }
        }

        $request->attributes->set('forwardTo', 'frontend.account.home.page');

        return $this->createActionResponse($request);
    }

    private function setNewsletterFlag(CustomerEntity $customer, bool $newsletter, SalesChannelContext $context): void
    {
        $this->customerRepository->update([
            'id' => $customer->getId(),
            'newsletter' => $newsletter,
        ], $context->getContext());
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());

        return $dataBag;
    }
}
