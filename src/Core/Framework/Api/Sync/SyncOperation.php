<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncOperation extends Struct
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $key;

    public function __construct(string $key, string $entity, string $action, array $payload)
    {
        $this->entity = $entity;
        $this->payload = $payload;
        $this->action = $action;
        $this->key = $key;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getApiAlias(): string
    {
        return 'api_sync_operation';
    }
}
