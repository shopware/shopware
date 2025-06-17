<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\UserService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\UserService\Token;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Token::class)]
class TokenTest extends TestCase
{
    public function testFromAndToJson(): void
    {
        $tokenValue = Uuid::randomHex();
        $refreshTokenValue = Uuid::randomHex();
        $token = Token::fromJson(\sprintf('{"token": "%s", "refreshToken": "%s"}', $tokenValue, $refreshTokenValue));

        static::assertSame($token->token, $tokenValue);
        static::assertSame($token->refreshToken, $refreshTokenValue);

        $result = \json_decode($token->toJson(), true);
        static::assertSame($tokenValue, $result['token']);
        static::assertSame($refreshTokenValue, $result['refreshToken']);
    }

    public function testFromArray(): void
    {
        $tokenValue = Uuid::randomHex();
        $refreshTokenValue = Uuid::randomHex();
        $token = Token::fromArray(['token' => $tokenValue, 'refreshToken' => $refreshTokenValue]);

        static::assertSame($token->token, $tokenValue);
        static::assertSame($token->refreshToken, $refreshTokenValue);
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validateTestDataProvider')]
    public function testValidate(array $data, string $expected): void
    {
        try {
            Token::fromArray($data);
        } catch (LoginException $exception) {
            static::assertSame($expected, $exception->getMessage());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            static::assertSame(LoginException::LOGIN_INVALID_REFRESH_OR_ACCESS_TOKEN, $exception->getErrorCode());
        }
    }

    /**
     * @return array<string, array{data: array<string, string>, expected: string}>
     */
    public static function validateTestDataProvider(): array
    {
        return [
            'test validate with empty array' => [
                'data' => [],
                'expected' => 'Invalid user Access or refresh token: [token]: This field is missing., [refreshToken]: This field is missing.',
            ],

            'test validate without token' => [
                'data' => ['refreshToken' => Uuid::randomHex()],
                'expected' => 'Invalid user Access or refresh token: [token]: This field is missing.',
            ],

            'test validate without token and empty refresh token' => [
                'data' => ['refreshToken' => ''],
                'expected' => 'Invalid user Access or refresh token: [token]: This field is missing., [refreshToken]: is required',
            ],

            'test validation without refresh token' => [
                'data' => ['token' => Uuid::randomHex()],
                'expected' => 'Invalid user Access or refresh token: [refreshToken]: This field is missing.',
            ],

            'test validate without refresh token and empty token' => [
                'data' => ['token' => ''],
                'expected' => 'Invalid user Access or refresh token: [token]: is required, [refreshToken]: This field is missing.',
            ],

            'test validate with empty token and empty refresh token' => [
                'data' => ['token' => '', 'refreshToken' => ''],
                'expected' => 'Invalid user Access or refresh token: [token]: is required, [refreshToken]: is required',
            ],
        ];
    }
}
