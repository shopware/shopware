<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class LoginException extends HttpException
{
    final public const LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED = 'LOGIN_CONFIG__INCOMPLETE_OR_MISCONFIGURED';
    final public const LOGIN_USER_NOT_FOUND = 'LOGIN__USER_NOT_FOUND';
    final public const LOGIN_CONFIG_NOT_FOUND = 'LOGIN__CONFIG_NOT_FOUND';
    final public const LOGIN_USER_INVALID = 'LOGIN__USER_INVALID';
    final public const LOGIN_INVALID_LOGIN_STATE = 'LOGIN__INVALID_LOGIN_STATE';
    final public const LOGIN_INVALID_TOKEN_RESPONSE = 'LOGIN__INVALID_TOKEN_RESPONSE';
    final public const LOGIN_INVALID_ID_TOKEN_DATA_SET = 'LOGIN__INVALID_ID_TOKEN_DATA_SET';
    final public const LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN = 'LOGIN__INVALID_REFRESH_OR_ACCESS_TOKEN';
    final public const LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED = 'LOGIN__INVALID_REQUEST_NO_CODE_PROVIDED';
    final public const LOGIN_PUBLIC_KEY_NOT_FOUND = 'LOGIN__PUBLIC_KEY_NOT_FOUND';
    final public const LOGIN_INVALID_ID_TOKEN = 'LOGIN__INVALID_ID_TOKEN';
    final public const LOGIN_INVALID_PUBLIC_KEY = 'LOGIN__INVALID_PUBLIC_KEY';

    private ?string $email;

    public function __construct(
        protected int $statusCode,
        protected string $errorCode,
        string $message,
        array $parameters = [],
        ?\Throwable $previous = null,
        ?string $email = null,
    ) {
        parent::__construct($statusCode, $errorCode, $message, $parameters, $previous);

        $this->email = $email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public static function configurationNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_NOT_FOUND,
            'Config not found'
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
            self::LOGIN_CONFIG_INCOMPLETE_OR_MISCONFIGURED,
            'Login config is incomplete or misconfigured. Field errors: {{ fieldErrors }}',
            ['fieldErrors' => $fields]
        );
    }

    public static function userNotFound(string $email): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_USER_NOT_FOUND,
            'User not found',
            [],
            null,
            $email
        );
    }

    /**
     * @param array<int, string> $missingFields
     */
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

    public static function invalidLoginState(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_INVALID_LOGIN_STATE,
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
            self::LOGIN_INVALID_TOKEN_RESPONSE,
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
            self::LOGIN_INVALID_ID_TOKEN_DATA_SET,
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
            self::LOGIN_INVALID_REQUEST_NO_CODE_PROVIDED,
            'Invalid request. Request does not provide a code',
        );
    }

    public static function publicKeyNotFound(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_PUBLIC_KEY_NOT_FOUND,
            'Public key not found',
        );
    }

    public static function invalidIdToken(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::LOGIN_INVALID_ID_TOKEN,
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
            self::LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN,
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
            self::LOGIN_INVALID_PUBLIC_KEY,
            'Got invalid JSON public keys. Got: {{ response }}',
            [
                'response' => $response,
            ]
        );
    }
}
