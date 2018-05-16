<?php declare(strict_types=1);

namespace Shopware\Storefront\Context;

use Shopware\Storefront\StorefrontRequest;
use Shopware\Application\Context\Util\StorefrontContextPersister;
use Shopware\Application\Context\Util\StorefrontContextService;
use Shopware\StorefrontApi\Firewall\CustomerUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class CustomerAuthenticationSubscriber implements EventSubscriberInterface
{
    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(StorefrontContextPersister $contextPersister)
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
            [StorefrontContextService::CUSTOMER_ID => $customerId]
        );
    }
}
