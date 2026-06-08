<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Checkout\Customer\Extension\SendRecoveryMailExtension;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles the password-recovery request yourself — generate and store your own
 * recovery token and send your own mail — instead of the core flow.
 */
readonly class SendRecoveryMailExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SendRecoveryMailExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(SendRecoveryMailExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data, $event->context, $event->validateStorefrontUrl

        $event->result = new SuccessResponse();

        // stop propagation so the core recovery flow is skipped
        $event->stopPropagation();
    }
}
