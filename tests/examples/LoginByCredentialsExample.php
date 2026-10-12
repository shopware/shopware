<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Checkout\Customer\Extension\LoginByCredentialsExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the customer login yourself — against an SSO, an employee sub-account
 * table, an external identity provider, … — instead of the core credential check.
 */
readonly class LoginByCredentialsExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LoginByCredentialsExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(LoginByCredentialsExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->email, $event->password, $event->context

        // verify the credentials against your own store and hand back a context token
        $event->result = 'your-context-token';

        // stop propagation so the core credential check is skipped
        $event->stopPropagation();
    }
}
