<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('data-services')]
class ConsentException extends HttpException
{
    final public const NOT_FOUND = 'SYSTEM__CONSENT_NOT_FOUND';
    final public const STORAGE_NOT_FOUND = 'SYSTEM__CONSENT_STORAGE_NOT_FOUND';
    final public const INVALID_CONSENT = 'SYSTEM__CONSENT_INVALID_CONSENT';
    final public const INVALID_CONSENT_STATUS = 'SYSTEM__CONSENT_INVALID_CONSENT_STATUS';
    final public const INVALID_SCOPE = 'SYSTEM__CONSENT_INVALID_SCOPE';

    final public const CANNOT_RESOLVE_ACTOR = 'SYSTEM__CONSENT_CANNOT_RESOLVE_ACTOR';

    final public const INSUFFICIENT_PERMISSIONS = 'SYSTEM__CONSENT_INSUFFICIENT_PERMISSIONS';

    public static function notFound(string $name): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND,
            'Consent with name "{{ name }}" not found.',
            ['name' => $name]
        );
    }

    /**
     * @param list<string> $options
     */
    public static function invalidStorage(string $storage, array $options): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STORAGE_NOT_FOUND,
            'Consent storage "{{ storage }}" not found. Available stores: {{ options }}.',
            [
                'storage' => $storage,
                'options' => implode(', ', $options),
            ],
        );
    }

    public static function invalidConsent(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CONSENT,
            'Consent is invalid.',
        );
    }

    public static function invalidConsentStatus(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CONSENT_STATUS,
            'Consent status is invalid.',
        );
    }

    public static function invalidScope(string $scope): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SCOPE,
            'No scope found with name "{{ scope }}".',
            ['scope' => $scope]
        );
    }

    public static function cannotResolveScope(string $scope): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SCOPE,
            'Scope with name "{{ scope }}" cannot be resolved with current context.',
            ['scope' => $scope]
        );
    }

    public static function cannotResolveActor(string $id): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_RESOLVE_ACTOR,
            'Cannot resolve actor with user id "{{ userId }}".',
            ['userId' => $id],
        );
    }

    /**
     * @param array<string> $missingPermissions
     */
    public static function insufficientPermissions(string $consent, array $missingPermissions): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::INSUFFICIENT_PERMISSIONS,
            \sprintf('Missing required permission to update consent "{{ consent }}". Missing permissions: %s', implode(', ', $missingPermissions)),
            [
                'consent' => $consent,
                'permission' => $missingPermissions,
            ],
        );
    }
}
