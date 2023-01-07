<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package core
 */
class SyncOperation extends Struct
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    protected string $entity;

    /**
     * @var array<int, mixed>
     */
    protected array $payload;

    protected string $action;

    protected string $key;

    /**
     * @param array<int, mixed> $payload
     */
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

    /**
     * @return array<int, mixed>
     */
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

    /**
     * @return string[]
     */
    public function getSupportedActions(): array
    {
        return [self::ACTION_UPSERT, self::ACTION_DELETE];
    }

    /**
     * @return array<string>
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->entity)) {
            $errors[] = sprintf(
                'Missing "entity" argument for operation with key "%s". It needs to be a non-empty string.',
                $this->key
            );
        }

        if (empty($this->action) || !\in_array($this->action, $this->getSupportedActions(), true)) {
            $errors[] = sprintf(
                'Missing or invalid "action" argument for operation with key "%s". Supported actions are [%s]',
                $this->key,
                implode(', ', $this->getSupportedActions())
            );
        }

        if (empty($this->payload)) {
            $errors[] = sprintf(
                'Missing "payload" argument for operation with key "%s". It needs to be a non-empty array.',
                $this->key
            );
        }

        return $errors;
    }
}
