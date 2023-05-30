<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class SyncOperation extends Struct
{
    final public const ACTION_UPSERT = 'upsert';
    final public const ACTION_DELETE = 'delete';

    /**
     * @param array<int, mixed> $payload
     * @param array<int, mixed> $criteria
     */
    public function __construct(
        protected string $key,
        protected string $entity,
        protected string $action,
        protected array $payload,
        protected array $criteria = []
    ) {
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

    /**
     * @internal used to replace payload in case of api shorthands (e.g. delete mappings with wild cards, etc)
     *
     * @param array<int, mixed> $payload
     */
    public function replacePayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return array<int, mixed> $criteria
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function hasCriteria(): bool
    {
        return !empty($this->criteria);
    }
}
