<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Listener;

use Shopware\Core\Checkout\Cart\Order\OrderRestorer;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('checkout')]
class OrderRestorationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AbstractOrderRoute $orderRoute,
        private readonly OrderRestorer $orderRestorer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['restoreOrderState', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST],
            ],
        ];
    }

    public function restoreOrderState(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION) !== true) {
            return;
        }

        $orderId = $this->getOrderId($request);
        if ($orderId === null) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        if ($context->getCustomer() === null) {
            throw OrderException::customerNotLoggedIn();
        }

        if (!\is_string($orderId) || !Uuid::isValid($orderId)) {
            throw OrderException::invalidUuid(\is_scalar($orderId) ? (string) $orderId : '');
        }

        $criteria = $this->orderRestorer->addRequiredAssociations(new Criteria([$orderId]));
        $order = $this->orderRoute->load(new Request(), $context, $criteria)->getOrders()->getEntities()->get($orderId);
        if ($order === null) {
            throw OrderException::orderNotFound($orderId);
        }

        $restored = $this->orderRestorer->restore($order, $context->getContext());

        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_SALES_CHANNEL_CONTEXT_OBJECT, $restored->context);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT, $restored->context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_CART_OBJECT, $restored->cart);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
        $request->attributes->remove(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        $request->attributes->set('orderId', $orderId);
    }

    private function getOrderId(Request $request): mixed
    {
        foreach ([$request->attributes, $request->query, $request->request] as $parameters) {
            if ($parameters->has('orderId')) {
                return $parameters->get('orderId');
            }
        }

        return null;
    }
}
