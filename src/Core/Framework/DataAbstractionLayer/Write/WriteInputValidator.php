<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class WriteInputValidator
{
    /**
     * @param array<array<string, mixed>> $data
     */
    public static function validate(array $data): void
    {
        if (!\array_is_list($data)) {
            throw DataAbstractionLayerException::invalidWriteInput('Input should contain a list of associative arrays.');
        }

        foreach ($data as $payload) {
            if (!\is_array($payload) || \array_is_list($payload) || self::hasNonStringKeys($payload)) {
                throw DataAbstractionLayerException::invalidWriteInput('Input should contain a list of associative arrays.');
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function hasNonStringKeys(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
