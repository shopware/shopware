<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
class AdminAuthException extends HttpException
{
    public const ENCRYPTION_FAILED = 'FRAMEWORK__ADMIN_AUTH_ENCRYPTION_FAILED';
    public const DECRYPTION_FAILED = 'FRAMEWORK__ADMIN_AUTH_DECRYPTION_FAILED';
    public const FEATURE_NOT_ACTIVE = 'FRAMEWORK__ADMIN_AUTH_FEATURE_NOT_ACTIVE';
    public const PROVIDER_NOT_FOUND = 'FRAMEWORK__ADMIN_AUTH_PROVIDER_NOT_FOUND';
    public const PROVIDER_NOT_WRITABLE = 'FRAMEWORK__ADMIN_AUTH_PROVIDER_NOT_WRITABLE';
    public const PROVIDER_MISCONFIGURED = 'FRAMEWORK__ADMIN_AUTH_PROVIDER_MISCONFIGURED';
    public const OIDC_DISCOVERY_FAILED = 'FRAMEWORK__ADMIN_AUTH_OIDC_DISCOVERY_FAILED';
    public const OIDC_TOKEN_RESPONSE_INVALID = 'FRAMEWORK__ADMIN_AUTH_OIDC_TOKEN_RESPONSE_INVALID';
    public const OIDC_ID_TOKEN_INVALID = 'FRAMEWORK__ADMIN_AUTH_OIDC_ID_TOKEN_INVALID';
    public const OIDC_LOGIN_FAILED = 'FRAMEWORK__ADMIN_AUTH_OIDC_LOGIN_FAILED';
    public const INVALID_OAUTH_STATE = 'FRAMEWORK__ADMIN_AUTH_INVALID_OAUTH_STATE';

    public static function encryptionFailed(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENCRYPTION_FAILED,
            'Failed to encrypt secret.'
        );
    }

    public static function decryptionFailed(?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DECRYPTION_FAILED,
            'Failed to decrypt secret.',
            [],
            $previous
        );
    }

    public static function featureNotActive(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::FEATURE_NOT_ACTIVE,
            'The ADMIN_AUTH feature is not active.'
        );
    }

    public static function providerNotFound(string $providerId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PROVIDER_NOT_FOUND,
            'Unknown or inactive admin auth provider "{{ providerId }}".',
            ['providerId' => $providerId]
        );
    }

    /**
     * Write-validation violation used to reject `admin_auth_provider` writes while providers are
     * managed via the YAML configuration or the admin UI is disabled.
     */
    public static function providersNotWritable(): WriteConstraintViolationException
    {
        $message = 'Admin auth providers are managed via the configuration file and cannot be modified through the API.';

        return new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation(
                $message,
                $message,
                [],
                null,
                '/',
                null,
                null,
                self::PROVIDER_NOT_WRITABLE
            ),
        ]));
    }

    public static function providerMisconfigured(string $label, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROVIDER_MISCONFIGURED,
            'The admin auth provider "{{ label }}" is misconfigured: {{ reason }}',
            ['label' => $label, 'reason' => $reason]
        );
    }

    public static function oidcDiscoveryFailed(string $url, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OIDC_DISCOVERY_FAILED,
            'Could not read the OpenID Connect discovery document at "{{ url }}".',
            ['url' => $url],
            $previous
        );
    }

    public static function oidcTokenResponseInvalid(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OIDC_TOKEN_RESPONSE_INVALID,
            'The OIDC token response did not contain an id_token.'
        );
    }

    public static function invalidIdToken(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OIDC_ID_TOKEN_INVALID,
            'Invalid id_token: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function oidcLoginFailed(string $reason): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OIDC_LOGIN_FAILED,
            'OIDC login failed: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function invalidOauthState(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_OAUTH_STATE,
            'Invalid or expired OAuth login state.'
        );
    }
}
