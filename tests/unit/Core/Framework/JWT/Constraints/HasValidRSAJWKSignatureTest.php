<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\Constraints\HasValidRSAJWKSignature;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\JWT\Struct\JWKStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type JSONWebKey from JWKStruct
 */
#[Package('checkout')]
#[CoversClass(HasValidRSAJWKSignature::class)]
class HasValidRSAJWKSignatureTest extends TestCase
{
    public function testAssert(): void
    {
        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $this->validate($jwt);
    }

    public function testAssertInvalidAlgorithm(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWT: Invalid algorithm (alg) in JWT header: "HS256"');

        $this->validate($this->getInvalidJwt('wrong-algorithm'));
    }

    public function testAssertMissingKey(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWT: Key ID (kid) missing from JWT header');

        $this->validate($this->getInvalidJwt('missing-kid'));
    }

    public function testAssertKidNotFound(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWT: Key ID (kid) could not be found');

        $this->validate($this->getInvalidJwt('not-found-kid'));
    }

    public function testAssertInvalidKeyType(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid key type: "ABCDEF"');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $jwks = $this->getValidJwks(['kty' => 'ABCDEF']);

        $this->validate($jwt, $jwks);
    }

    public function testAssertInvalidBase64UrlEncodedE(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWK: Invalid base64 characters detected');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $jwks = $this->getValidJwks(['e' => 'ABCD%EF']);

        $this->validate($jwt, $jwks);
    }

    public function testAssertInvalidBase64UrlEncodedN(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWK: Invalid base64 characters detected');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $jwks = $this->getValidJwks(['n' => 'ABCD%EF']);

        $this->validate($jwt, $jwks);
    }

    /**
     * @param array{keys: array<int, JSONWebKey>}|null $jwks
     */
    private function validate(string $token, ?array $jwks = null): void
    {
        static::assertNotEmpty($token);

        $jwks ??= $this->getValidJwks();

        $validator = new HasValidRSAJWKSignature(JWKCollection::fromArray($jwks));

        $parser = new Parser(new JoseEncoder());

        $validator->assert($parser->parse($token));
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array{keys: list<JSONWebKey>}
     */
    private function getValidJwks(array $override = []): array
    {
        $jwks = file_get_contents(__DIR__ . '/../_fixtures/valid-jwks.json');
        static::assertIsString($jwks);
        $jwks = json_decode($jwks, true, 512, \JSON_THROW_ON_ERROR);
        $jwks['keys'][0] = array_merge($jwks['keys'][0], $override);

        return $jwks;
    }

    private function getInvalidJwt(string $index): string
    {
        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/invalid-jwts.json');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $jwts = json_decode($jwt, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($jwts);

        return $jwts[$index][0];
    }
}
