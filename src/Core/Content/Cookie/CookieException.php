<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Cookie;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CookieException extends HttpException
{
    final public const NOT_ALLOWED_PROPERTY_ASSIGNMENT = 'CONTENT__COOKIE_NOT_ALLOWED_PROPERTY_ASSIGNMENT';

    public static function notAllowedPropertyAssignment(string $propertyToBeAssigned, string $alreadyAssignedProperty): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::NOT_ALLOWED_PROPERTY_ASSIGNMENT,
            'Property "{{ propertyToBeAssigned }}" cannot be set, as "{{ alreadyAssignedProperty }}" is already set.',
            ['propertyToBeAssigned' => $propertyToBeAssigned, 'alreadyAssignedProperty' => $alreadyAssignedProperty],
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed as it will be unused with the next major version
     *
     * @param array<string, mixed> $cookieGroup
     */
    public static function invalidLegacyCookieGroupProvided(array $cookieGroup): self
    {
        try {
            $encodedCookieGroup = json_encode($cookieGroup, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $encodedCookieGroup = 'Could not encode cookie group to JSON';
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            'CONTENT__COOKIE_INVALID_LEGACY_COOKIE_GROUP_PROVIDED',
            'Invalid legacy cookie group provided: {{ cookieGroup }}. The key "snippet_name" is required.',
            ['cookieGroup' => $encodedCookieGroup],
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed as it will be unused with the next major version
     *
     * @param array<string, mixed> $entry
     */
    public static function invalidLegacyCookieEntryProvided(array $entry): self
    {
        try {
            $encodedEntry = json_encode($entry, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $encodedEntry = 'Could not encode cookie entry to JSON';
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            'CONTENT__COOKIE_INVALID_LEGACY_COOKIE_ENTRY_PROVIDED',
            'Invalid legacy cookie entry provided: {{ entry }}. The key "cookie" is required.',
            ['entry' => $encodedEntry],
        );
    }
}
