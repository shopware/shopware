<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Contracts\Service\ResetInterface;

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
class WebhookSigningSecretResolver implements ResetInterface
{
    /**
     * Per-run cache, keyed by app id, of the secret looked up from the database. Cleared between
     * worker messages / requests via reset(), so delivering a batch of webhooks for the same app
     * does not query the secret once per message, while a later rotation is still picked up.
     *
     * @var array<string, ?string>
     */
    private array $appSecrets = [];

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly DeletedAppsGateway $deletedAppsGateway,
    ) {
    }

    public function resolve(WebhookEventMessage $message): ?string
    {
        $appId = $message->getAppId();
        if ($appId === null) {
            return $message->getSecret();
        }

        if (!\array_key_exists($appId, $this->appSecrets)) {
            $this->appSecrets[$appId] = $this->currentSecret($appId) ?? $this->deletedAppSecret($message->getAppName());
        }

        // Older queued messages still carry the secret; use it until the queue has drained.
        return $this->appSecrets[$appId] ?? $message->getSecret();
    }

    public function reset(): void
    {
        $this->appSecrets = [];
    }

    private function currentSecret(string $appId): ?string
    {
        $app = $this->appRepository->search(new Criteria([$appId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        return $this->emptyToNull($app?->getAppSecret());
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
