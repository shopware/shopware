<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\JWT;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\Constraints\HasValidRSAJWKSignature;
use Shopware\Core\Framework\JWT\Constraints\MatchesLicenceDomain;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(JWTDecoder::class)]
class JWTDecoderTest extends TestCase
{
    private JWTDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new JWTDecoder();
    }

    public function testDecodeWithValidToken(): void
    {
        $claims = $this->decoder->decode($this->getJwt());
        static::assertSame([
            ['identifier' => 'Purchase1', 'nextBookingDate' => '2099-12-13 11:44:31', 'quantity' => 1, 'sub' => 'example.com'],
            ['identifier' => 'Purchase2', 'nextBookingDate' => '2099-12-13 11:44:31', 'quantity' => 1, 'sub' => 'example.com'],
        ], $claims);
    }

    public function testValidateWithValidToken(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN)
            ->willReturn('example.com');

        $jwks = file_get_contents(__DIR__ . '/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);
        $jwks = json_decode($jwks, true, 512, \JSON_THROW_ON_ERROR);
        $jwks = JWKCollection::fromArray($jwks);

        $signatureValidator = new HasValidRSAJWKSignature($jwks);
        $domainValidator = new MatchesLicenceDomain($systemConfigService);

        $this->decoder->validate($this->getJwt(), $signatureValidator, $domainValidator);
    }

    #[DataProvider('provideInvalidJwts')]
    public function testValidateWithInvalidToken(string $invalidJwt, string $expectedExceptionMessage): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::atMost(1))
            ->method('get')
            ->with(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN)
            ->willReturn('example.com');

        $jwks = file_get_contents(__DIR__ . '/_fixtures/valid-jwks.json');
        static::assertIsString($jwks);
        $jwks = json_decode($jwks, true, 512, \JSON_THROW_ON_ERROR);
        $jwks = JWKCollection::fromArray($jwks);

        $signatureValidator = new HasValidRSAJWKSignature($jwks);
        $domainValidator = new MatchesLicenceDomain($systemConfigService);

        $this->decoder->validate($invalidJwt, $signatureValidator, $domainValidator);
    }

    public function testDecodeWithInvalidTokenThrowsException(): void
    {
        $this->expectException(JWTException::class);
        $this->expectExceptionMessage('Invalid JWT: Error while decoding from Base64Url, invalid base64 characters detected');
        $this->decoder->decode('invalid.jwt.token');
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function provideInvalidJwts(): array
    {
        $jwts = \file_get_contents(__DIR__ . '/_fixtures/invalid-jwts.json');
        static::assertIsString($jwts);

        return \json_decode($jwts, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function getJwt(): string
    {
        $jwt = \file_get_contents(__DIR__ . '/_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        return $jwt;
    }
}
