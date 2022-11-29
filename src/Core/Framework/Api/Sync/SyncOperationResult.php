<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @package core
 *
 * @deprecated tag:v6.6.0 - Will be removed, as it is not used anymore
 */
class SyncOperationResult extends Struct
{
    /**
     * @var array<mixed>
     */
    protected $result;

    /**
     * @param array<mixed> $result
     */
    public function __construct(array $result)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        $this->result = $result;
    }

    /**
     * @return array<mixed>
     */
    public function getResult(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $this->result;
    }

    public function hasError(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        foreach ($this->result as $result) {
            if (\count($result['errors']) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|int $key
     *
     * @return array<mixed>|null
     */
    public function get($key): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $this->result[$key] ?? null;
    }

    /**
     * @param string|int $key
     */
    public function has($key): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return isset($this->result[$key]);
    }

    public function resetEntities(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        foreach ($this->result as $index => $_writeResult) {
            $this->result[$index]['entities'] = [];
        }
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return 'api_sync_operation_result';
    }
}
