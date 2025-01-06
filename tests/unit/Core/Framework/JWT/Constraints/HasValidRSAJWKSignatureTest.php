<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\Constraints\HasValidRSAJWKSignature;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
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
        self::expectException(JWTException::class);
        self::expectExceptionMessage('Invalid JWT: Invalid algorithm (alg) in JWT header: "HS256"');

        $this->validate($this->getInvalidJwt('wrong-algorithm'));
    }

    public function testAssertMissingKey(): void
    {
        self::expectException(JWTException::class);
        self::expectExceptionMessage('Invalid JWT: Key ID (kid) missing from JWT header');

        $this->validate($this->getInvalidJwt('missing-kid'));
    }

    public function testAssertKidNotFound(): void
    {
        self::expectException(JWTException::class);
        self::expectExceptionMessage('Invalid JWT: Key ID (kid) could not be found');

        $this->validate($this->getInvalidJwt('not-found-kid'));
    }

    public function testAssertInvalidKeyType(): void
    {
        self::expectException(JWTException::class);
        self::expectExceptionMessage('Invalid key type: "ABCDEF"');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $this->validate($jwt, '{"keys":[{"kty":"ABCDEF","n":"6irjzX6gF6Q3l2wC6VPVpPQ48-n48aIxQ2RjYKRY-1uxpRcnyE1X7aCEFFypY6XFVZ5_wvyj84sKPwnB8vGKyPENFAwn4HLlNYU71J-ruPsbFteHNtYMD1WaO6f-pouqnsdkeIpwiM5fc0SXpdMpNBbqNWzdPEQKmQrI3BQ6RP6TsRVrjzDt4ucCzwgRGWezsHHrLIWTuRR1hvm8FIr8-0ZRsYu9gkgwvsJzpdJzfRJrOb6-NWcWM6QowWyVl1v4Nu7Tcb-qTrAUG-e71duaI3erfE0YFFx130BOZzelHgUdRhqnHVpSkWLz9aTT6-xtDd5Y2iMi7Em-LGzdvQAkDw","e":"AQAB","kid":"ce86f11b0bebb0b711394663c17f0013","use":"sig","alg":"RS256"}]}');
    }

    private function validate(string $token, ?string $jwks = null): void
    {
        static::assertNotEmpty($token);

        if (!$jwks) {
            $jwks = file_get_contents(__DIR__ . '/../_fixtures/valid-jwks.json');
            static::assertIsString($jwks);
        }
        $jwks = json_decode($jwks, true, 512, \JSON_THROW_ON_ERROR);
        $jwks = JWKCollection::fromArray($jwks);

        $validator = new HasValidRSAJWKSignature($jwks);

        $parser = new Parser(new JoseEncoder());

        $validator->assert($parser->parse($token));
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
