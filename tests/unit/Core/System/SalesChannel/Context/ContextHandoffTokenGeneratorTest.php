<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffToken;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextHandoffTokenGenerator::class)]
class ContextHandoffTokenGeneratorTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0146543d6a6241718da05d5ee6f6891a';

    private Configuration $jwtConfiguration;

    private ContextHandoffTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->jwtConfiguration = JWTConfigurationFactory::createJWTConfiguration();
        $this->generator = new ContextHandoffTokenGenerator(
            $this->jwtConfiguration,
            new DataValidator(Validation::createValidator())
        );
    }

    public function testEncodedTokenCanBeDecoded(): void
    {
        $jti = Uuid::randomHex();

        $decoded = $this->generator->decode($this->generator->encode($this->createToken($jti)));

        static::assertSame($jti, $decoded->jti);
        static::assertSame(self::SALES_CHANNEL_ID, $decoded->salesChannelId);
        static::assertSame([ContextHandoffTokenGenerator::AUDIENCE], $decoded->aud);
    }

    public function testEncodedTokenDoesNotCarryAnyOtherClaims(): void
    {
        $token = $this->createToken(Uuid::randomHex());

        $parsed = $this->jwtConfiguration->parser()->parse($this->generator->encode($token));
        static::assertInstanceOf(UnencryptedToken::class, $parsed);

        static::assertSame(
            ['iat', 'nbf', 'exp', 'aud', 'jti', 'salesChannelId'],
            array_keys($parsed->claims()->all())
        );
    }

    public function testLifetimeIsAMinute(): void
    {
        $token = new ContextHandoffToken();
        $token->salesChannelId = self::SALES_CHANNEL_ID;
        $token->aud = [ContextHandoffTokenGenerator::AUDIENCE];

        $this->generator->encode($token);

        static::assertNotNull($token->iat);
        static::assertNotNull($token->exp);
        static::assertSame(
            ContextHandoffTokenGenerator::TOKEN_LIFETIME_IN_SECONDS,
            $token->exp->getTimestamp() - $token->iat->getTimestamp()
        );
    }

    public function testDecodeRejectsAnExpiredToken(): void
    {
        $token = $this->createToken(Uuid::randomHex());
        $token->iat = new \DateTimeImmutable('-10 minutes');
        $token->nbf = new \DateTimeImmutable('-10 minutes');
        $token->exp = new \DateTimeImmutable('-5 minutes');

        $this->expectException(JWTException::class);
        $this->generator->decode($this->generator->encode($token));
    }

    public function testDecodeRejectsAForeignAudience(): void
    {
        $token = $this->createToken(Uuid::randomHex());
        $token->aud = ['some-other-consumer'];

        $this->expectException(JWTException::class);
        $this->generator->decode($this->generator->encode($token));
    }

    public function testDecodeRejectsAMissingAudience(): void
    {
        $token = $this->createToken(Uuid::randomHex());
        $token->aud = null;

        $this->expectException(JWTException::class);
        $this->generator->decode($this->generator->encode($token));
    }

    public function testDecodeRejectsAMissingSalesChannelId(): void
    {
        $token = $this->createToken(Uuid::randomHex());
        $token->salesChannelId = null;

        $this->expectException(ConstraintViolationException::class);
        $this->generator->decode($this->generator->encode($token));
    }

    public function testDecodeRejectsATokenSignedWithAnotherKey(): void
    {
        $foreignGenerator = new ContextHandoffTokenGenerator(
            Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText(str_repeat('a-completely-different-secret', 2))
            ),
            new DataValidator(Validation::createValidator())
        );

        $this->expectException(JWTException::class);
        $this->generator->decode($foreignGenerator->encode($this->createToken(Uuid::randomHex())));
    }

    private function createToken(string $jti): ContextHandoffToken
    {
        $token = new ContextHandoffToken();
        $token->jti = $jti;
        $token->aud = [ContextHandoffTokenGenerator::AUDIENCE];
        $token->salesChannelId = self::SALES_CHANNEL_ID;

        return $token;
    }
}
