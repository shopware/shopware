<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.5.0 will be removed, as it is not needed if we remove the `IncrementStorageInterface`
 */
class IncrementStorageMigrationNotSupportedException extends ShopwareHttpException
{
    public function __construct(string $legacyStorage)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        parent::__construct(
            'The legacy number range increment storage "{{ storage }}" does not support migrations.',
            ['storage' => $legacyStorage]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return 'FRAMEWORK__INCREMENT_STORAGE_MIGRATION_NOT_SUPPORTED';
    }
}
