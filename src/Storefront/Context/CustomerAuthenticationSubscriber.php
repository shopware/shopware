<?php declare(strict_types=1);

namespace Shopware\Storefront\Context;

use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\Routing\Firewall\CustomerUser;
use Shopware\Storefront\StorefrontRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class CustomerAuthenticationSubscriber implements EventSubscriberInterface
{
    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    public function __construct(CheckoutContextPersister $contextPersister)
    {
        $this->contextPersister = $contextPersister;
    }

    public static function getSubscribedEvents()
    {
        return [
//            SecurityEvents::INTERACTIVE_LOGIN => [
//                ['updateContext']
//            ]
        ];
    }

    public function updateContext(InteractiveLoginEvent $event)
    {
        if ($event->getRequest()->attributes->has(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST) === false) {
            return;
        }

        $customerId = null;

        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof CustomerUser) {
            $customerId = $user->getId();
        }

        $contextKey = $event->getRequest()->attributes->get(ApplicationResolver::CONTEXT_HEADER);

        $this->contextPersister->save(
            $contextKey,
            [CheckoutContextService::CUSTOMER_ID => $customerId]
        );
    }
}
