<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Makes the MFA pending token powerless against the whole admin API.
 *
 * Shopware authorizes admin reads via the user's ACL roles, not the token's OAuth scopes, so simply
 * withholding the `write`/`admin` scopes from the pending token is not enough — it could still read.
 * This guard runs right after the core bearer-token validation (CONTROLLER, AUTH_VALIDATE_POST) and
 * rejects any request whose validated token carries the `admin-mfa-pending` scope. The pending token
 * is therefore only ever usable to complete the second factor at the public /api/oauth/token endpoint
 * (which is auth_required:false and never reaches this guard).
 *
 * @internal
 */
#[Package('framework')]
class PendingTokenGuard implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['rejectPendingToken', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_POST],
            ],
        ];
    }

    public function rejectPendingToken(ControllerEvent $event): void
    {
        if (!Feature::isActive('ADMIN_AUTH')) {
            return;
        }

        $scopes = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES);
        if (!\is_array($scopes)) {
            return;
        }

        if (\in_array(MfaPendingScope::IDENTIFIER, $scopes, true)) {
            throw OAuthServerException::accessDenied('This token is only valid to complete multi-factor authentication.');
        }
    }
}
