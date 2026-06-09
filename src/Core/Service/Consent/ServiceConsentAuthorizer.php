<?php declare(strict_types=1);

namespace Shopware\Core\Service\Consent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\HookableAuthorizer;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * Gates the service-consent webhook events so only services (self-managed apps) receive them.
 *
 * @internal
 */
#[Package('framework')]
class ServiceConsentAuthorizer implements HookableAuthorizer
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function supports(Hookable $event): bool
    {
        return ($event instanceof ConsentAcceptedEvent || $event instanceof ConsentRevokedEvent)
            && $event->consentName === ServiceConsent::NAME;
    }

    public function isAllowed(Hookable $event, string $appId, AclPrivilegeCollection $permissions): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT `self_managed` FROM `app` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($appId)],
        );
    }
}
