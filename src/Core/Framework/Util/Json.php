<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;

#[Package('framework')]
final class Json
{
    /**
     * Mariadbs `JSON_VALID` function does not allow escaped unicode.
     */
    public static function encode(mixed $value, int $options = \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_IGNORE): string
    {
        return (string) json_encode($value, $options);
    }

    /**
     * @throws JsonDecodingException when the JSON is invalid, not an array or not an object with sequential keys
     *
     * @return list<mixed>
     */
    public static function decodeToList(string $value, bool $allowEmpty = true): array
    {
        if ($value === '' && $allowEmpty) {
            return [];
        }

        try {
            $result = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw UtilException::invalidJson($e);
        }

        if (\is_array($result) && \array_is_list($result)) {
            return $result;
        }

        throw UtilException::invalidJsonNotList();
    }

    /**
     * @throws JsonDecodingException when the JSON is invalid or cannot be decoded to an array
     *
     * @return array<array-key, mixed>
     */
    public static function decodeToArray(string $value): array
    {
        try {
            $result = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw UtilException::invalidJson($e);
        }

        if (\is_array($result)) {
            return $result;
        }

        throw UtilException::invalidJsonNotArray();
    }
}
