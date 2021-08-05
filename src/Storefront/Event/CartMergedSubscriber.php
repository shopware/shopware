<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class CartMergedSubscriber implements EventSubscriberInterface
{
    private TranslatorInterface $translator;

    private RequestStack $requestStack;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack
    ) {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
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
