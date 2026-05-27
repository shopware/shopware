<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Profile\UrlSafetyValidator;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * SSRF / DNS-rebinding / metadata-endpoint hardening for platform-profile
 * fetches. Each branch is an attacker surface: a failure to reject any of
 * these URL shapes lets a hostile platform pull credentials from the
 * Shopware host's cloud metadata endpoint, scan its private network, or
 * trick the resolver via punycode homographs.
 *
 * Pinning the rejection behaviour per threat (T1–T7 from the source) is
 * the only way to catch regressions to this attacker surface.
 *
 * @internal
 */
#[CoversClass(UrlSafetyValidator::class)]
class UrlSafetyValidatorTest extends TestCase
{
    public function testAcceptsHttpsPublicHostInProduction(): void
    {
        $validator = new UrlSafetyValidator();
        // 8.8.8.8 is a public DNS, guaranteed not to be RFC-1918 / loopback.
        $result = $validator->validateAndResolve('https://8.8.8.8', null, 'prod');

        static::assertSame('8.8.8.8', $result['host']);
        static::assertSame('8.8.8.8', $result['resolved_ip']);
    }

    public function testAcceptsHttpInLocalDevForLoopback(): void
    {
        $validator = new UrlSafetyValidator();
        $result = $validator->validateAndResolve('http://127.0.0.1', null, 'dev');

        static::assertSame('127.0.0.1', $result['host']);
    }

    public function testRejectsHttpInProduction(): void
    {
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('http://shop.example.com', null, 'prod');
    }

    public function testRejectsUrlsWithUserInfo(): void
    {
        // T4 — userinfo embedding (RFC 3986 user@host form).
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('https://user:pass@shop.example.com', null, 'prod');
    }

    public function testRejectsNonDefaultHttpsPort(): void
    {
        // T5 — only 443 is allowed for HTTPS.
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('https://shop.example.com:8443', null, 'prod');
    }

    public function testRejectsUrlsWithUnparsableShape(): void
    {
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('not-a-url', null, 'prod');
    }

    public function testRejectsCloudMetadataIpDirectly(): void
    {
        // T3 — cloud metadata endpoint must be unreachable.
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('https://169.254.169.254', null, 'dev');
    }

    public function testRejectsRfc1918PrivateRangeInProd(): void
    {
        // T1 — private range must be rejected in prod even though they
        // pass plain validate_ip checks.
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve('https://10.0.0.1', null, 'prod');
    }

    public function testAcceptsRfc1918InDevForDockerContainers(): void
    {
        $validator = new UrlSafetyValidator();
        $result = $validator->validateAndResolve('http://10.0.0.1', null, 'dev');

        static::assertSame('10.0.0.1', $result['resolved_ip']);
    }

    public function testRejectsHostNotInAllowlist(): void
    {
        // T7 — allowlist gate must hold for any non-matching public host.
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve(
            'https://attacker.example',
            ['platform.example', 'partner.example'],
            'prod'
        );
    }

    public function testAcceptsExplicitlyAllowlistedHost(): void
    {
        $validator = new UrlSafetyValidator();
        $result = $validator->validateAndResolve(
            'https://8.8.8.8',
            ['8.8.8.8'],
            'prod'
        );

        static::assertSame('8.8.8.8', $result['host']);
    }

    public function testAcceptsWildcardSubdomainAllowlist(): void
    {
        // Allowlist wildcard matches the public IP form here just to exercise
        // the matcher without needing a working DNS resolver in CI.
        $validator = new UrlSafetyValidator();
        $result = $validator->validateAndResolve(
            'https://8.8.8.8',
            ['*.unrelated.example', '8.8.8.8'],
            'prod'
        );

        static::assertSame('8.8.8.8', $result['host']);
    }

    public function testNormalisesTrailingDotInHost(): void
    {
        // FQDN normalisation per RFC 3986 §3.2.2.
        $validator = new UrlSafetyValidator();
        $result = $validator->validateAndResolve('https://8.8.8.8.', null, 'prod');

        static::assertSame('8.8.8.8', $result['host']);
    }

    #[DataProvider('cloudMetadataHostnamesProvider')]
    public function testRejectsCloudMetadataHostnamesAtAnyEnv(string $url): void
    {
        $this->expectException(UcpException::class);
        (new UrlSafetyValidator())->validateAndResolve($url, null, 'dev');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function cloudMetadataHostnamesProvider(): iterable
    {
        yield 'aws_ec2_v4' => ['https://169.254.169.254'];
        yield 'gce_metadata_host' => ['https://metadata.google.internal'];
    }
}
