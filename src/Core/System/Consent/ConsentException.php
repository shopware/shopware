<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('data-services')]
class ConsentException extends HttpException
{
    final public const NOT_FOUND = 'SYSTEM__CONSENT_NOT_FOUND';
    final public const ALREADY_EXISTS = 'SYSTEM__CONSENT_ALREADY_EXISTS';
    final public const STORAGE_NOT_FOUND = 'SYSTEM__CONSENT_STORAGE_NOT_FOUND';
    final public const INVALID_CONSENT = 'SYSTEM__CONSENT_INVALID_CONSENT';
    final public const INVALID_CONSENT_STATUS = 'SYSTEM__CONSENT_INVALID_CONSENT_STATUS';
    final public const INVALID_SCOPE = 'SYSTEM__CONSENT_INVALID_SCOPE';
    final public const IDENTIFIER_REQUIRED = 'SYSTEM__CONSENT_IDENTIFIER_REQUIRED';

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
            'No scope resolver found for scope "{{ scope }}".',
            ['scope' => $scope]
        );
    }

    public static function identifierRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::IDENTIFIER_REQUIRED,
            'Consents with non global scope require an identifier.'
        );
    }
}
