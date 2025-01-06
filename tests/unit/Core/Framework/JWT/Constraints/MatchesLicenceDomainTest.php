<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\Constraints\MatchesLicenceDomain;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MatchesLicenceDomain::class)]
class MatchesLicenceDomainTest extends TestCase
{
    public function testAssert(): void
    {
        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $this->validate($jwt);
    }

    public function testAssertNoDomainSet(): void
    {
        static::expectException(JWTException::class);
        static::expectExceptionMessage('Missing domain');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/valid-jwt.txt');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $this->validate($jwt, '');
    }

    public function testAssertInvalidDomain(): void
    {
        static::expectException(JWTException::class);
        static::expectExceptionMessage('Invalid domain in system configuration: "examples.com"');

        $jwt = \file_get_contents(__DIR__ . '/../_fixtures/invalid-jwts.json');
        static::assertIsString($jwt);
        $jwt = \trim($jwt);

        $jwts = json_decode($jwt, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($jwts);

        $this->validate(array_values($jwts)[3][0]);
    }

    private function validate(string $token, string $returnDomain = 'example.com'): void
    {
        static::assertNotEmpty($token);

        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects(static::once())
            ->method('get')
            ->with(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN)
            ->willReturn($returnDomain);

        $validator = new MatchesLicenceDomain($configService);

        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($token);

        $validator->assert($token);
    }
}
