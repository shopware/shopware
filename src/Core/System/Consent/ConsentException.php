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

    public static function notFound(string $name): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND,
            'Consent with name "{{ name }}" not found.',
            ['name' => $name]
        );
    }

    public static function alreadyExists(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ALREADY_EXISTS,
            'Consent with name "{{ name }}" already exists.',
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
}
