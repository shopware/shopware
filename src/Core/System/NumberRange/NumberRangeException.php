<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\Exception\IncrementStorageNotFoundException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class NumberRangeException extends HttpException
{
    public const INCREMENT_STORAGE_NOT_FOUND = 'FRAMEWORK__INCREMENT_STORAGE_NOT_FOUND';

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     *
     * @param array<string> $availableStorages
     */
    public static function incrementStorageNotFound(string $storage, array $availableStorages): self|IncrementStorageNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new IncrementStorageNotFoundException($storage, $availableStorages);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INCREMENT_STORAGE_NOT_FOUND,
            'The number range increment storage "{{ storage }}" is not available. Available storages are: "{{ availableStorages }}".',
            ['storage' => $storage, 'availableStorages' => implode('", "', $availableStorages)]
        );
    }
}
