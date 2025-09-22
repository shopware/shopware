<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Exceptions\SsoUserNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SsoException extends HttpException
{
    public const SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND = 'SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND';
    public const SSO_LOGIN_CONFIG_NOT_FOUND = 'SSO_LOGIN_CONFIG__NOT_FOUND';
    public const SSO_LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED = 'SSO_LOGIN_CONFIG__INCOMPLETE_OR_MISCONFIGURED';

    public const SSO_LOGIN_USER_INVALID = 'SSO_LOGIN__USER_INVALID';
    public const SSO_LOGIN_INVALID_LOGIN_STATE = 'SSO_LOGIN__INVALID_LOGIN_STATE';
    public const SSO_LOGIN_INVALID_TOKEN_RESPONSE = 'SSO_LOGIN__INVALID_TOKEN_RESPONSE';
    public const SSO_LOGIN_INVALID_ID_TOKEN_DATA_SET = 'SSO_LOGIN__INVALID_ID_TOKEN_DATA_SET';
    public const SSO_LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN = 'SSO_LOGIN__INVALID_REFRESH_OR_ACCESS_TOKEN';
    public const SSO_LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED = 'SSO_LOGIN__INVALID_REQUEST_NO_CODE_PROVIDED';
    public const SSO_LOGIN_PUBLIC_KEY_NOT_FOUND = 'SSO_LOGIN__PUBLIC_KEY_NOT_FOUND';
    public const SSO_LOGIN_INVALID_ID_TOKEN = 'SSO_LOGIN__INVALID_ID_TOKEN';
    public const SSO_LOGIN_INVALID_PUBLIC_KEY = 'SSO_LOGIN__INVALID_PUBLIC_KEY';
    public const SSO_LOGIN_NEGATIVE_TIME_TO_LIVE = 'SSO_LOGIN__NEGATIVE_TIME_TO_LIVE';
    public const SSO_LOGIN_TOKEN_NOT_FOUND = 'SSO_LOGIN__TOKEN_NOT_FOUND';
    public const SSO_LOGIN_REFERER_NOT_FOUND = 'FRAMEWORK__SSO_LOGIN_REFERER_NOT_FOUND';

    public static function mailTemplateNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND,
            'Mail template for sso user invitation not found'
        );
    }

    public static function loginConfigurationNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_CONFIG_NOT_FOUND,
            'Login configuration not found'
        );
    }

    /**
     * @param array<string> $fieldErrors
     */
    public static function configurationMisconfigured(array $fieldErrors): self
    {
        $fields = implode(', ', $fieldErrors);

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED,
            'Login config is incomplete or misconfigured. Field errors: {{ fieldErrors }}',
            ['fieldErrors' => $fields]
        );
    }

    public static function userNotFound(string $email): SsoUserNotFoundException
    {
        return new SsoUserNotFoundException($email);
    }

    /**
     * @param array<int, string> $missingFields
     */
    public static function loginUserInvalid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_USER_INVALID,
            'Login user invalid: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    public static function invalidLoginState(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_LOGIN_STATE,
            'Invalid login state'
        );
    }

    /**
     * @param array<int, string> $missingFields
     */
    public static function tokenNotValid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_TOKEN_RESPONSE,
            'Token not valid. Missing: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    /**
     * @param array<int, string> $violations
     */
    public static function invalidIdTokenDataSet(array $violations): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_ID_TOKEN_DATA_SET,
            'ID-Token not valid: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $violations),
            ]
        );
    }

    public static function noCodeProvided(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED,
            'Invalid request. Request does not provide a code',
        );
    }

    public static function publicKeyNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_PUBLIC_KEY_NOT_FOUND,
            'Public key not found',
        );
    }

    public static function invalidIdToken(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_ID_TOKEN,
            'The id token is invalid',
        );
    }

    /**
     * @param array<int, string> $violations
     */
    public static function invalidRefreshOrAccessToken(array $violations): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN,
            'Invalid user Access or refresh token: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $violations),
            ]
        );
    }

    public static function invalidPublicKey(string $response): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_INVALID_PUBLIC_KEY,
            'Got invalid JSON public keys. Got: {{ response }}',
            [
                'response' => $response,
            ]
        );
    }

    public static function negativeTimeToLive(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_LOGIN_NEGATIVE_TIME_TO_LIVE,
            'Token time to live should not be negative',
        );
    }

    public static function tokenNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_TOKEN_NOT_FOUND,
            'Cannot get token from user.',
        );
    }

    public static function refererNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_REFERER_NOT_FOUND,
            'Referrer not found. Cannot redirect.',
        );
    }
}
