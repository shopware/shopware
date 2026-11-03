<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Builder\Webhook;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 * Inserts the webhook row directly, without the write validation of the Admin API.
 * An app-less webhook is only loaded for delivery when it has an owner.
 *
 * How to use:
 * $webhookId = WebhookBuilder::ownedByUser($userId)
 *          ->subscribe('product.written', 'https://example.com/hook')
 *          ->insert($connection);
 */
#[Package('framework')]
class WebhookBuilder
{
    private string $id;

    private ?string $name = null;

    private string $eventName = 'product.written';

    private string $url = 'https://example.com/webhook';

    private bool $active = true;

    private int $errorCount = 0;

    /**
     * @var list<string>|null
     */
    private ?array $aclRoleIds = null;

    private function __construct(
        private readonly ?string $appId = null,
        private readonly ?string $ownerUserId = null,
        private readonly ?string $ownerIntegrationId = null,
    ) {
        $this->id = Uuid::randomHex();
    }

    public static function ownedByUser(string $userId): self
    {
        return new self(ownerUserId: $userId);
    }

    public static function ownedByIntegration(string $integrationId): self
    {
        return new self(ownerIntegrationId: $integrationId);
    }

    public static function ofApp(string $appId): self
    {
        return new self(appId: $appId);
    }

    public static function withoutOwner(): self
    {
        return new self();
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function subscribe(string $eventName, string $url): self
    {
        $this->eventName = $eventName;
        $this->url = $url;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function inactive(): self
    {
        $this->active = false;

        return $this;
    }

    public function errorCount(int $errorCount): self
    {
        $this->errorCount = $errorCount;

        return $this;
    }

    /**
     * @param list<string> $aclRoleIds
     */
    public function aclRoleIds(array $aclRoleIds): self
    {
        $this->aclRoleIds = $aclRoleIds;

        return $this;
    }

    /**
     * @return string the webhook id
     */
    public function insert(Connection $connection): string
    {
        $connection->insert('webhook', [
            'id' => Uuid::fromHexToBytes($this->id),
            'name' => $this->name ?? 'webhook-' . $this->id,
            'event_name' => $this->eventName,
            'url' => $this->url,
            'app_id' => $this->appId !== null ? Uuid::fromHexToBytes($this->appId) : null,
            'owner_user_id' => $this->ownerUserId !== null ? Uuid::fromHexToBytes($this->ownerUserId) : null,
            'owner_integration_id' => $this->ownerIntegrationId !== null ? Uuid::fromHexToBytes($this->ownerIntegrationId) : null,
            'active' => (int) $this->active,
            'error_count' => $this->errorCount,
            'acl_role_ids' => $this->aclRoleIds !== null ? json_encode($this->aclRoleIds, \JSON_THROW_ON_ERROR) : null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $this->id;
    }
}
