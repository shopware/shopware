<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * Picks the secret an outgoing webhook is signed with, at the moment it is sent.
 *
 * App secrets can change (they get rotated). A webhook can wait in the queue or be retried long
 * after it was created, so the secret stored on the message may be out of date. Reading the current
 * secret at send time keeps the signature valid for the receiving app.
 *
 * @internal
 */
#[Package('framework')]
class WebhookSigningSecretResolver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DeletedAppsGateway $deletedAppsGateway,
    ) {
    }

    public function resolve(WebhookEventMessage $message): ?string
    {
        $appId = $message->getAppId();
        if ($appId === null) {
            return $message->getSecret();
        }

        return $this->currentSecret($appId)
            ?? $this->deletedAppSecret($message->getAppName())
            // Older queued messages still carry the secret; use it until the queue has drained.
            ?? $message->getSecret();
    }

    private function currentSecret(string $appId): ?string
    {
        $secret = $this->connection->fetchOne(
            'SELECT `app_secret` FROM `app` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($appId)]
        );

        return \is_string($secret) ? $this->emptyToNull($secret) : null;
    }

    // The app was uninstalled but still has webhooks in flight; its secret is kept in deleted_apps.
    private function deletedAppSecret(?string $appName): ?string
    {
        if ($appName === null) {
            return null;
        }

        return $this->emptyToNull($this->deletedAppsGateway->getDeletedAppSecret($appName));
    }

    private function emptyToNull(?string $secret): ?string
    {
        return $secret === '' ? null : $secret;
    }
}
