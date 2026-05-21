<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Jwt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\EcKeyGenerator;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(EcKeyGenerator::class)]
class EcKeyGeneratorTest extends TestCase
{
    public function testGeneratesValidEs256Keypair(): void
    {
        $gen = new EcKeyGenerator();
        $result = $gen->generate(UcpSigningKeyEntity::ALGORITHM_ES256);

        static::assertSame(UcpSigningKeyEntity::ALGORITHM_ES256, $result['algorithm']);
        static::assertNotEmpty($result['kid']);
        static::assertSame('EC', $result['public_jwk']['kty']);
        static::assertSame('P-256', $result['public_jwk']['crv']);
        static::assertSame($result['kid'], $result['public_jwk']['kid']);
        static::assertStringContainsString('PRIVATE KEY', $result['private_key_pem']);
    }

    public function testGeneratesValidEs384Keypair(): void
    {
        $gen = new EcKeyGenerator();
        $result = $gen->generate(UcpSigningKeyEntity::ALGORITHM_ES384);

        static::assertSame('P-384', $result['public_jwk']['crv']);
        static::assertSame(UcpSigningKeyEntity::ALGORITHM_ES384, $result['public_jwk']['alg']);
    }

    public function testUnknownAlgorithmRejected(): void
    {
        $this->expectException(UcpException::class);
        (new EcKeyGenerator())->generate('HS256');
    }

    public function testKidsAreReasonablyDistinct(): void
    {
        $gen = new EcKeyGenerator();
        $a = $gen->generate(UcpSigningKeyEntity::ALGORITHM_ES256);
        $b = $gen->generate(UcpSigningKeyEntity::ALGORITHM_ES256);
        static::assertNotSame($a['kid'], $b['kid']);
    }

    public function testBase64UrlRoundtrip(): void
    {
        $bytes = random_bytes(32);
        $encoded = EcKeyGenerator::base64UrlEncode($bytes);
        static::assertSame($bytes, EcKeyGenerator::base64UrlDecode($encoded));
        static::assertStringNotContainsString('=', $encoded);
        static::assertStringNotContainsString('+', $encoded);
        static::assertStringNotContainsString('/', $encoded);
    }
}
