<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Checkout\Customer\Extension\ResetPasswordExtension;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the password reset yourself — validate the hash against your own
 * store and update the password there — instead of the core flow.
 */
readonly class ResetPasswordExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ResetPasswordExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(ResetPasswordExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data (hash + new password), $event->context

        $event->result = new SuccessResponse();

        // stop propagation so the core reset flow is skipped
        $event->stopPropagation();
    }
}
