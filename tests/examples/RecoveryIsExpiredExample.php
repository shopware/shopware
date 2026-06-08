<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Checkout\Customer\Extension\RecoveryIsExpiredExtension;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decides whether a recovery hash is expired from your own store instead of
 * the core customer_recovery lookup.
 */
readonly class RecoveryIsExpiredExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RecoveryIsExpiredExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(RecoveryIsExpiredExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data (the recovery hash), $event->context

        $event->result = new CustomerRecoveryIsExpiredResponse(false);

        // stop propagation so the core expiry check is skipped
        $event->stopPropagation();
    }
}
