<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('checkout')]
class IncrementStorageNotFoundException extends ShopwareHttpException
{
    /**
     * @param array<string> $availableStorages
     */
    public function __construct(
        string $configuredStorage,
        array $availableStorages
    ) {
        parent::__construct(
            'The number range increment storage "{{ configuredStorage }}" is not available. Available storages are: "{{ availableStorages }}".',
            [
                'configuredStorage' => $configuredStorage,
                'availableStorages' => implode('", "', $availableStorages),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INCREMENT_STORAGE_NOT_FOUND';
    }
}
