<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\TokenService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ParsedIdToken::class)]
class ParsedIdTokenTest extends TestCase
{
    public function testCreateFromIdToken(): void
    {
        $token = (new FakeTokenGenerator())->generate();

        $result = ParsedIdToken::createFromIdToken($token);

        static::assertSame('fake-subject', $result->sub);
        static::assertSame('fake@email.com', $result->email);
    }

    /**
     * @param array<int, string>|null $audience
     */
    #[DataProvider('createFromIdTokenWithInvalidFieldsTestDataProvider')]
    public function testCreateFromIdTokenWithInvalidFields(
        ?array $audience,
        ?string $issuer,
        ?\DateTimeImmutable $issuedAt,
        ?\DateTimeImmutable $expiredAt,
        ?string $subject,
        ?string $email,
        string $expected
    ): void {
        try {
            $tokenGenerator = (new FakeTokenGenerator())
                ->setAudience($audience)
                ->setIssuer($issuer)
                ->setIssuedAt($issuedAt)
                ->setExpiresAt($expiredAt)
                ->setSubject($subject)
                ->setEmail($email);

            ParsedIdToken::createFromIdToken($tokenGenerator->generate());
        } catch (LoginException $exception) {
            static::assertSame($expected, $exception->getMessage());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $exception->getStatusCode());
            static::assertSame(LoginException::LOGIN_INVALID_ID_TOKEN_RESPONSE, $exception->getErrorCode());
        }
    }

    /**
     * @return array<string, array{audience: array<int, string>|null, issuer: string|null, issuedAt: \DateTimeImmutable, expiredAt: \DateTimeImmutable, subject: string|null, email: string|null, expected: string}>
     */
    public static function createFromIdTokenWithInvalidFieldsTestDataProvider(): array
    {
        return [
            'all is null' => [
                'audience' => null,
                'issuer' => null,
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => null,
                'email' => null,
                'expected' => 'ID-Token not valid. Missing: [aud], [iss], [sub], [email]',
            ],

            'all is blank' => [
                'audience' => [],
                'issuer' => '',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => '',
                'email' => '',
                'expected' => 'ID-Token not valid. Missing: [aud], [iss], [sub], [email]',
            ],

            'audience is set' => [
                'audience' => ['audience'],
                'issuer' => '',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => '',
                'email' => '',
                'expected' => 'ID-Token not valid. Missing: [iss], [sub], [email]',
            ],

            'issuer is set' => [
                'audience' => [],
                'issuer' => 'issuer',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => '',
                'email' => '',
                'expected' => 'ID-Token not valid. Missing: [aud], [sub], [email]',
            ],

            'subject is set' => [
                'audience' => [],
                'issuer' => '',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => 'subject',
                'email' => '',
                'expected' => 'ID-Token not valid. Missing: [aud], [iss], [email]',
            ],

            'email is set but invalid' => [
                'audience' => [],
                'issuer' => '',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => '',
                'email' => 'invalid email',
                'expected' => 'ID-Token not valid. Missing: [aud], [iss], [sub], [email]',
            ],

            'email is set and valid' => [
                'audience' => [],
                'issuer' => '',
                'issuedAt' => new \DateTimeImmutable(),
                'expiredAt' => new \DateTimeImmutable(),
                'subject' => '',
                'email' => 'test@example.com',
                'expected' => 'ID-Token not valid. Missing: [aud], [iss], [sub]',
            ],
        ];
    }
}
