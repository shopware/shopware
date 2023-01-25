<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @deprecated tag:v6.6.0 - Will be removed, as it is not used anymore
 */
#[Package('core')]
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
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
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
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return $this->result;
    }

    public function hasError(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        foreach ($this->result as $result) {
            if ((is_countable($result['errors']) ? \count($result['errors']) : 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>|null
     */
    public function get(string|int $key): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return $this->result[$key] ?? null;
    }

    public function has(string|int $key): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return isset($this->result[$key]);
    }

    public function resetEntities(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        foreach ($this->result as $index => $_writeResult) {
            $this->result[$index]['entities'] = [];
        }
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0')
        );

        return 'api_sync_operation_result';
    }
}
