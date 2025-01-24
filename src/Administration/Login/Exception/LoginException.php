<?php


namespace Shopware\Administration\Login\Exception;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class LoginException extends HttpException
{
    final public const LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED = 'LOGIN_CONFIG__INCOMPLETE_OR_MISCONFIGURED';
    final public const LOGIN_USER_NOT_FOUND = 'LOGIN__USER_NOT_FOUND';
    final public const LOGIN_RATE_LIMIT_EXCEEDED = 'LOGIN__RATE_LIMIT_EXCEEDED';
    final public const LOGIN_USER_INVALID = 'LOGIN__USER_INVALID';
    final public const LOGIN_INVALID_LOGIN_STATE = 'LOGIN__INVALID_LOGIN_STATE';
    final public const LOGIN_INVALID_TOKEN_RESPONSE = 'LOGIN__INVALID_TOKEN_RESPONSE';
    final public const LOGIN_INVALID_ID_TOKEN_RESPONSE = 'LOGIN__INVALID_ID_TOKEN_RESPONSE';

    /**
     * @param array<string> $fieldErrors
     */
    public static function configurationMisconfigured(array $fieldErrors): self
    {
        $fields = implode(', ', $fieldErrors);

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED,
            'Login config is incomplete or misconfigured. Field errors: {{ fieldErrors }}',
            ['fieldErrors' => $fields]
        );
    }

    public static function userNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_USER_NOT_FOUND,
            'User not found'
        );
    }

    public static function loginUserInvalid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_USER_INVALID,
            'Login user invalid: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    public static function rateLimitExceeded(RateLimitExceededException $previous): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_RATE_LIMIT_EXCEEDED,
            'Wait for {{ seconds }} seconds',
            [
                'seconds' => $previous->getWaitTime(),
            ],
            $previous
        );
    }

    public static function invalidLoginState(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_INVALID_LOGIN_STATE,
            'Invalid login state'
        );
    }

    public static function tokenNotValid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_INVALID_TOKEN_RESPONSE,
            'Token not valid. Missing: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }

    public static function idTokenNotValid(array $missingFields): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_INVALID_ID_TOKEN_RESPONSE,
            'ID-Token not valid. Missing: {{ missingFields }}',
            [
                'missingFields' => \implode(', ', $missingFields),
            ]
        );
    }
}
