<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('storefront')]
class CartMergedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartMergedEvent::class => 'addCartMergedNoticeFlash',
        ];
    }

    public function addCartMergedNoticeFlash(CartMergedEvent $event): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        if ($mainRequest->hasSession() === false) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!method_exists($session, 'getFlashBag')) {
            return;
        }

        $session->getFlashBag()->add('info', $this->translator->trans('checkout.cart-merged-hint'));
    }
}
