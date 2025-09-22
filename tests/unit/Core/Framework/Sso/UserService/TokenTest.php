<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\UserService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\UserService\Token;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Token::class)]
class TokenTest extends TestCase
{
    public function testJsonSerializable(): void
    {
        $tokenValue = Uuid::randomHex();
        $refreshTokenValue = Uuid::randomHex();
        $token = Token::fromArray([
            'token' => $tokenValue,
            'refreshToken' => $refreshTokenValue,
        ]);

        static::assertSame($token->token, $tokenValue);
        static::assertSame($token->refreshToken, $refreshTokenValue);

        $result = \json_decode(\json_encode($token, \JSON_THROW_ON_ERROR), true);
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
        $this->expectExceptionObject(new SsoException(0, '0', $expected));

        Token::fromArray($data);
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
