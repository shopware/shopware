<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Requirements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;

/**
 * @internal
 */
#[CoversClass(SecureUrlValidator::class)]
class SecureUrlValidatorTest extends TestCase
{
    #[DataProvider('urlValidationProvider')]
    public function testUrlValidation(string $url, bool $expected): void
    {
        $validator = new SecureUrlValidator(static fn (string $host): array => [['ip' => '8.8.8.8']]);

        static::assertSame($expected, $validator->isValidTarget($url));
    }

    public static function urlValidationProvider(): \Generator
    {
        // Valid URLs
        yield 'HTTPS domain' => ['https://shopware.com', true];
        yield 'HTTPS domain with port' => ['https://shopware.com:8443', true];
        yield 'HTTPS domain with path' => ['https://shopware.com/path', true];
        yield 'HTTPS subdomain' => ['https://shop.shopware.com', true];
        yield 'HTTPS domain with query params' => ['https://shopware.com?param=value', true];
        yield 'HTTPS domain with fragment' => ['https://shopware.com#fragment', true];
        yield 'HTTPS domain with complex path' => ['https://shopware.com/path/to/resource', true];

        // Invalid URLs - HTTP instead of HTTPS
        yield 'HTTP URL' => ['http://shopware.com', false];
        yield 'HTTP with port' => ['http://shopware.com:8080', false];

        // Invalid URLs - No scheme or wrong scheme
        yield 'No scheme' => ['shopware.com', false];
        yield 'Protocol relative' => ['//shopware.com', false];
        yield 'FTP scheme' => ['ftp://shopware.com', false];
        yield 'File scheme' => ['file://shopware.com', false];

        // Invalid URLs - IP addresses
        yield 'IPv4 address' => ['https://192.168.1.1', false];
        yield 'IPv4 with port' => ['https://192.168.1.1:8080', false];
        yield 'IPv6 address' => ['https://[2001:db8::1]', false];
        yield 'IPv6 with port' => ['https://[2001:db8::1]:8080', false];
        yield 'Loopback IPv4' => ['https://127.0.0.1', false];
        yield 'Loopback IPv6' => ['https://[::1]', false];

        // Invalid URLs - Localhost variations
        yield 'Localhost' => ['https://localhost', false];
        yield 'Localhost with port' => ['https://localhost:8080', false];
        yield 'Localhost uppercase' => ['https://LOCALHOST', false];
        yield 'Localhost mixed case' => ['https://LocalHost', false];

        // Invalid URLs - Reserved IANA special-use domains
        yield '.test TLD' => ['https://myshop.test', false];
        yield '.local TLD' => ['https://myshop.local', false];
        yield '.localhost subdomain' => ['https://shop.localhost', false];
        yield '.example TLD' => ['https://myshop.example', false];
        yield '.invalid TLD' => ['https://myshop.invalid', false];
        yield '.onion TLD' => ['https://hidden.onion', false];
        yield '.home.arpa TLD' => ['https://mydevice.home.arpa', false];
        yield 'example.com exact' => ['https://example.com', false];
        yield 'example.net exact' => ['https://example.net', false];
        yield 'example.org exact' => ['https://example.org', false];
        yield 'home.arpa exact' => ['https://home.arpa', false];
        yield 'localdomain exact' => ['https://localdomain', false];
        yield 'nested .test subdomain' => ['https://deep.sub.myshop.test', false];

        // Invalid URLs - Reserved domains with trailing dot (FQDN notation)
        yield 'Localhost with trailing dot' => ['https://localhost.', false];
        yield '.test with trailing dot' => ['https://myshop.test.', false];
        yield '.local with trailing dot' => ['https://myshop.local.', false];
        yield 'example.com with trailing dot' => ['https://example.com.', false];
        yield 'example.net with trailing dot' => ['https://example.net.', false];

        // Invalid URLs - Malformed
        yield 'Invalid URL' => ['not-a-url', false];
        yield 'Empty string' => ['', false];
        yield 'Only scheme' => ['https://', false];
    }

    public function testPublicIpv4Passes(): void
    {
        $validator = new SecureUrlValidator(static fn (string $host): array => [['ip' => '8.8.8.8']]);

        static::assertTrue($validator->isValidTarget('https://shopware.com'));
    }

    public function testPublicIpv6Passes(): void
    {
        $validator = new SecureUrlValidator(static fn (string $host): array => [['ipv6' => '2001:4860:4860::8888']]);

        static::assertTrue($validator->isValidTarget('https://shopware.com'));
    }

    public function testUnresolvableHostFails(): void
    {
        $validator = new SecureUrlValidator(static fn (string $host): array => []);

        static::assertFalse($validator->isValidTarget('https://shopware.com'));
    }

    /**
     * @param array{ip?: string, ipv6?: string} $record
     */
    #[DataProvider('nonPublicIpProvider')]
    public function testNonPublicIpFails(array $record): void
    {
        $validator = new SecureUrlValidator(static fn (string $host): array => [$record]);

        static::assertFalse($validator->isValidTarget('https://shopware.com'));
    }

    public static function nonPublicIpProvider(): \Generator
    {
        yield 'IPv4 loopback' => [['ip' => '127.0.0.1']];
        yield 'IPv4 class A private' => [['ip' => '10.0.0.1']];
        yield 'IPv4 class B private' => [['ip' => '172.16.0.1']];
        yield 'IPv4 class C private' => [['ip' => '192.168.1.1']];
        yield 'IPv6 loopback' => [['ipv6' => '::1']];
        yield 'IPv6 link-local' => [['ipv6' => 'fe80::1']];
        yield 'IPv6 unique local' => [['ipv6' => 'fd00::1']];
    }
}
