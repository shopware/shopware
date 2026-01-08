<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequestAttribute;

/**
 * @internal
 */
#[Package('framework')]
class CanonicalLinkListener
{
    public function __invoke(BeforeSendResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(SalesChannelRequestAttribute::CANONICAL_LINK->value)) {
            \assert(\is_string($canonical));
            $canonical = \sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }
}
