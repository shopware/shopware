<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Newsletter\Account;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractAccountNewsletterRecipientRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\RouteRequest\AccountNewsletterRecipientRouteRequestEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('customer-order')]
class NewsletterAccountPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractNewsletterSubscribeRoute $newsletterSubscribeRoute,
        private readonly AbstractNewsletterUnsubscribeRoute $newsletterUnsubscribeRoute,
        private readonly AbstractAccountNewsletterRecipientRoute $newsletterRecipientRoute,
        private readonly Translator $translator,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): NewsletterAccountPagelet {
        $newsletterAccountPagelet = $this->getBasePagelet($customer, $context->getSalesChannelId());

        $newsletterAccountPagelet->setNewsletterStatus(
            $this->getNewsletterRecipientStatus($request, $customer, $context)
        );

        if ($newsletterAccountPagelet->getNewsletterStatus() === NewsletterSubscribeRoute::STATUS_NOT_SET) {
            $text = $this->translator->trans('newsletter.subscriptionPersistedInfo');
            $newsletterAccountPagelet->addMessages(
                [
                    [
                        'type' => 'info',
                        'text' => $text,
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    public function action(
        Request $request,
        RequestDataBag $dataBag,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): NewsletterAccountPagelet {
        $subscribeOptions = [NewsletterSubscribeRoute::OPTION_DIRECT, NewsletterSubscribeRoute::OPTION_SUBSCRIBE];
        $doSubscribe = \in_array($request->get('option', false), $subscribeOptions, true);

        if (!$doSubscribe) {
            $dataBag->set('option', NewsletterSubscribeRoute::OPTION_UNSUBSCRIBE);
        }

        $dataBag->set('storefrontUrl', $request->attributes->get(RequestTransformer::STOREFRONT_URL));

        $newsletterAccountPagelet = $this->getBasePagelet($customer, $context->getSalesChannelId());

        if ($doSubscribe) {
            $newsletterAccountPagelet = $this->subscribe($dataBag, $customer, $context, $newsletterAccountPagelet);
        } else {
            $newsletterAccountPagelet = $this->unsubscribe($dataBag, $customer, $context, $newsletterAccountPagelet);
        }

        $newsletterAccountPagelet->setNewsletterStatus(
            $this->getNewsletterRecipientStatus($request, $customer, $context)
        );

        if ($newsletterAccountPagelet->getNewsletterStatus() === NewsletterSubscribeRoute::STATUS_NOT_SET) {
            $text = $this->translator->trans('newsletter.subscriptionPersistedInfo');
            $newsletterAccountPagelet->addMessages(
                [
                    [
                        'type' => 'info',
                        'text' => $text,
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function subscribe(RequestDataBag $dataBag, CustomerEntity $customer, SalesChannelContext $context, NewsletterAccountPagelet $newsletterAccountPagelet): NewsletterAccountPagelet
    {
        try {
            $this->newsletterSubscribeRoute->subscribe(
                $this->hydrateFromCustomer($dataBag, $customer),
                $context,
                false
            );

            $newsletterAccountPagelet->setSuccess(true);
            if ($newsletterAccountPagelet->isNewsletterDoi()) {
                $text = $this->translator->trans('newsletter.subscriptionPersistedSuccess');
            } else {
                $text = $this->translator->trans('newsletter.subscriptionConfirmationSuccess');
            }
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'success',
                        'text' => $text,
                    ],
                ]
            );
        } catch (\Exception) {
            $newsletterAccountPagelet->setSuccess(false);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'danger',
                        'text' => $this->translator->trans('newsletter.subscriptionConfirmationFailed'),
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function unsubscribe(RequestDataBag $dataBag, CustomerEntity $customer, SalesChannelContext $context, NewsletterAccountPagelet $newsletterAccountPagelet): NewsletterAccountPagelet
    {
        try {
            $this->newsletterUnsubscribeRoute->unsubscribe(
                $this->hydrateFromCustomer($dataBag, $customer),
                $context
            );

            $newsletterAccountPagelet->setSuccess(true);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'success',
                        'text' => $this->translator->trans('newsletter.subscriptionRevokeSuccess'),
                    ],
                ]
            );
        } catch (\Exception) {
            $newsletterAccountPagelet->setSuccess(false);
            $newsletterAccountPagelet->setMessages(
                [
                    [
                        'type' => 'danger',
                        'text' => $this->translator->trans('error.message-default'),
                    ],
                ]
            );
        }

        return $newsletterAccountPagelet;
    }

    protected function getNewsletterRecipientStatus(
        Request $request,
        CustomerEntity $customer,
        SalesChannelContext $context
    ): string {
        $criteria = new Criteria();
        $apiRequest = new Request();

        $event = new AccountNewsletterRecipientRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        $responseStruct = $this->newsletterRecipientRoute
            ->load($event->getStoreApiRequest(), $context, $criteria, $customer);

        $status = 'undefined';
        if ($responseStruct->getAccountNewsletterRecipient()->getStatus()) {
            $status = $responseStruct->getAccountNewsletterRecipient()->getStatus();
        }

        return $status;
    }

    protected function getBasePagelet(CustomerEntity $customer, string $salesChannelId): NewsletterAccountPagelet
    {
        $newsletterAccountPagelet = new NewsletterAccountPagelet();
        $newsletterAccountPagelet->setCustomer($customer);
        $newsletterAccountPagelet->setNewsletterDoi(
            (bool) $this->systemConfigService->get('core.newsletter.doubleOptInRegistered', $salesChannelId)
        );

        return $newsletterAccountPagelet;
    }

    private function hydrateFromCustomer(RequestDataBag $dataBag, CustomerEntity $customer): RequestDataBag
    {
        $dataBag->set('email', $customer->getEmail());
        $dataBag->set('salutationId', $customer->getSalutationId());
        $dataBag->set('title', $customer->getTitle());
        $dataBag->set('firstName', $customer->getFirstName());
        $dataBag->set('lastName', $customer->getLastName());
        $dataBag->set(
            'zipCode',
            ($customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getZipCode() : '')
        );
        $dataBag->set(
            'city',
            ($customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getCity() : '')
        );
        $dataBag->set(
            'street',
            ($customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getStreet() : '')
        );

        return $dataBag;
    }
}
