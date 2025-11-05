<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\JWT\SalesChannel;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\RelatedTo;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator;
use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataValidator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(JWTGenerator::class)]
class JWTGeneratorTest extends TestCase
{
    public function testEncodeAppliesClaimsBranches(): void
    {
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Random::getAlphanumericString(32)));
        $dataValidator = $this->createMock(DataValidator::class);

        $jwtStructClass = (new class extends JWTStruct {
            public string $foo;

            public ?string $nullValue;
        })::class;

        $generator = new class($config, $dataValidator, $jwtStructClass) extends JWTGenerator {
            /**
             * @param class-string<JWTStruct> $jwtStructClass
             */
            public function __construct(
                Configuration $configuration,
                DataValidator $validator,
                private readonly string $jwtStructClass
            ) {
                parent::__construct($configuration, $validator);
            }

            protected function getJWTStructClass(): string
            {
                return $this->jwtStructClass;
            }
        };

        $jwt = new $jwtStructClass([
            RegisteredClaims::AUDIENCE => ['audA'],
            RegisteredClaims::SUBJECT => 'subject-1',
            RegisteredClaims::ISSUER => 'issuer-1',
            RegisteredClaims::ID => 'id-123',
            'foo' => 'bar',
            'nullValue' => null,
            // Date claims should be ignored in the foreach (handled earlier)
            RegisteredClaims::ISSUED_AT => new \DateTimeImmutable('-1 minute'),
            RegisteredClaims::NOT_BEFORE => new \DateTimeImmutable('-1 minute'),
            RegisteredClaims::EXPIRATION_TIME => new \DateTimeImmutable('+1 hour'),
        ]);

        $tokenString = $generator->encode($jwt);
        $token = $config->parser()->parse($tokenString);
        static::assertInstanceOf(UnencryptedToken::class, $token);

        $claims = $token->claims();
        static::assertSame('issuer-1', $claims->get(RegisteredClaims::ISSUER));
        static::assertSame('subject-1', $claims->get(RegisteredClaims::SUBJECT));
        static::assertSame('id-123', $claims->get(RegisteredClaims::ID));
        static::assertSame(['audA'], $claims->get(RegisteredClaims::AUDIENCE));
        static::assertSame('bar', $claims->get('foo'));
        static::assertInstanceOf(\DateTimeImmutable::class, $claims->get(RegisteredClaims::ISSUED_AT));
        static::assertInstanceOf(\DateTimeImmutable::class, $claims->get(RegisteredClaims::NOT_BEFORE));
        static::assertInstanceOf(\DateTimeImmutable::class, $claims->get(RegisteredClaims::EXPIRATION_TIME));
    }

    public function testEncodeUsesDefaultLifetimeForDates(): void
    {
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Random::getAlphanumericString(32)));
        $dataValidator = $this->createMock(DataValidator::class);

        $jwtStructClass = (new class extends JWTStruct {
        })::class;

        $generator = new class($config, $dataValidator, $jwtStructClass) extends JWTGenerator {
            /**
             * @param class-string<JWTStruct> $jwtStructClass
             */
            public function __construct(
                Configuration $configuration,
                DataValidator $validator,
                private readonly string $jwtStructClass
            ) {
                parent::__construct($configuration, $validator);
            }

            protected function getJWTStructClass(): string
            {
                return $this->jwtStructClass;
            }
        };

        $jwt = new $jwtStructClass([]); // no explicit dates -> use defaults

        $tokenString = $generator->encode($jwt);
        $token = $config->parser()->parse($tokenString);
        static::assertInstanceOf(UnencryptedToken::class, $token);

        $claims = $token->claims();
        $iat = $claims->get(RegisteredClaims::ISSUED_AT);
        $nbf = $claims->get(RegisteredClaims::NOT_BEFORE);
        $exp = $claims->get(RegisteredClaims::EXPIRATION_TIME);

        static::assertInstanceOf(\DateTimeImmutable::class, $iat);
        static::assertInstanceOf(\DateTimeImmutable::class, $nbf);
        static::assertInstanceOf(\DateTimeImmutable::class, $exp);

        static::assertSame($iat->getTimestamp(), $nbf->getTimestamp(), 'nbf should equal iat when not provided');
        static::assertSame(3600, $exp->getTimestamp() - $iat->getTimestamp(), 'exp should be iat + 3600 by default');
    }

    public function testDecodeThrowsOnEmptyToken(): void
    {
        // use real configuration (final class)
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Random::getAlphanumericString(32)));
        $dataValidator = $this->createMock(DataValidator::class);

        $jwtStructClass = (new class extends JWTStruct {
        })::class;

        $generator = new class($config, $dataValidator, $jwtStructClass) extends JWTGenerator {
            /**
             * @param class-string<JWTStruct> $jwtStructClass
             */
            public function __construct(
                Configuration $configuration,
                DataValidator $validator,
                private readonly string $jwtStructClass
            ) {
                parent::__construct($configuration, $validator);
            }

            protected function getJWTStructClass(): string
            {
                return $this->jwtStructClass;
            }
        };

        $this->expectException(JWTException::class);
        $generator->decode('');
    }

    public function testDecodeThrowsWhenValidationFails(): void
    {
        // Real configuration and real validator with failing constraint (aud mismatch)
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Random::getAlphanumericString(32)));
        $config = $config->withValidationConstraints(
            new SignedWith($config->signer(), $config->verificationKey()),
            new LooseValidAt(SystemClock::fromUTC()),
            new PermittedFor('expected-aud') // will fail
        );

        // Build a real token that doesn't satisfy the PermittedFor constraint
        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+1 hour'))
            ->identifiedBy('id-x')
            ->permittedFor('wrong-aud')
            ->relatedTo('sub-x')
            ->issuedBy('iss-x')
            ->withClaim('k', 'v')
            ->getToken($config->signer(), $config->signingKey());

        $dataValidator = $this->createMock(DataValidator::class);

        $jwtStructClass = (new class extends JWTStruct {
        })::class;

        $generator = new class($config, $dataValidator, $jwtStructClass) extends JWTGenerator {
            /**
             * @param class-string<JWTStruct> $jwtStructClass
             */
            public function __construct(
                Configuration $configuration,
                DataValidator $validator,
                private readonly string $jwtStructClass
            ) {
                parent::__construct($configuration, $validator);
            }

            protected function getJWTStructClass(): string
            {
                return $this->jwtStructClass;
            }
        };

        $this->expectException(JWTException::class);
        $generator->decode($token->toString());
    }

    public function testDecodeReturnsAssignedStructOnSuccess(): void
    {
        // Real configuration and constraints that match the token
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText(Random::getAlphanumericString(32)));

        $now = new \DateTimeImmutable();
        $token = $config->builder()
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+1 hour'))
            ->identifiedBy('id-ok')
            ->permittedFor('aud-ok')
            ->relatedTo('sub-ok')
            ->issuedBy('iss-ok')
            ->withClaim('foo', 'bar')
            ->getToken($config->signer(), $config->signingKey());

        // Ensure all constraints succeed
        $config = $config->withValidationConstraints(
            new SignedWith($config->signer(), $config->verificationKey()),
            new LooseValidAt(SystemClock::fromUTC()),
            new PermittedFor('aud-ok'),
            new IssuedBy('iss-ok'),
            new RelatedTo('sub-ok'),
            new IdentifiedBy('id-ok')
        );

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator->expects($this->once())->method('validate');

        $jwtStructClass = (new class extends JWTStruct {
            public string $foo;
        })::class;

        $generator = new class($config, $dataValidator, $jwtStructClass) extends JWTGenerator {
            /**
             * @param class-string<JWTStruct> $jwtStructClass
             */
            public function __construct(
                Configuration $configuration,
                DataValidator $validator,
                private readonly string $jwtStructClass
            ) {
                parent::__construct($configuration, $validator);
            }

            protected function getJWTStructClass(): string
            {
                return $this->jwtStructClass;
            }
        };

        $result = $generator->decode($token->toString());
        static::assertInstanceOf($jwtStructClass, $result);

        $vars = $result->getVars();
        static::assertSame('iss-ok', $vars[RegisteredClaims::ISSUER] ?? null);
        static::assertSame('sub-ok', $vars[RegisteredClaims::SUBJECT] ?? null);
        static::assertSame('id-ok', $vars[RegisteredClaims::ID] ?? null);
        static::assertSame('bar', $vars['foo'] ?? null);
        static::assertArrayHasKey(RegisteredClaims::ISSUED_AT, $vars);
        static::assertArrayHasKey(RegisteredClaims::NOT_BEFORE, $vars);
        static::assertArrayHasKey(RegisteredClaims::EXPIRATION_TIME, $vars);
    }
}
