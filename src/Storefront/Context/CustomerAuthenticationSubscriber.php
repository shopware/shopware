<?php declare(strict_types=1);

namespace Shopware\Storefront\Context;

use Shopware\Checkout\Customer\Util\CustomerContextPersister;
use Shopware\Checkout\Customer\Util\CustomerContextService;
use Shopware\Framework\Routing\Firewall\CustomerUser;
use Shopware\Storefront\StorefrontRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class CustomerAuthenticationSubscriber implements EventSubscriberInterface
{
    /**
     * @var CustomerContextPersister
     */
    private $contextPersister;

    public function __construct(CustomerContextPersister $contextPersister)
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
            [CustomerContextService::CUSTOMER_ID => $customerId]
        );
    }
}
