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
        $validator = new SecureUrlValidator();

        static::assertSame($expected, $validator->isValidTarget($url));
    }

    public static function urlValidationProvider(): \Generator
    {
        // Valid URLs
        yield 'HTTPS domain' => ['https://example.com', true];
        yield 'HTTPS domain with port' => ['https://example.com:8443', true];
        yield 'HTTPS domain with path' => ['https://example.com/path', true];
        yield 'HTTPS subdomain' => ['https://shop.example.com', true];
        yield 'HTTPS domain with query params' => ['https://example.com?param=value', true];
        yield 'HTTPS domain with fragment' => ['https://example.com#fragment', true];
        yield 'HTTPS domain with complex path' => ['https://example.com/path/to/resource', true];

        // Invalid URLs - HTTP instead of HTTPS
        yield 'HTTP URL' => ['http://example.com', false];
        yield 'HTTP with port' => ['http://example.com:8080', false];

        // Invalid URLs - No scheme or wrong scheme
        yield 'No scheme' => ['example.com', false];
        yield 'Protocol relative' => ['//example.com', false];
        yield 'FTP scheme' => ['ftp://example.com', false];
        yield 'File scheme' => ['file://example.com', false];

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
        yield 'example.net exact' => ['https://example.net', false];
        yield 'example.org exact' => ['https://example.org', false];
        yield 'home.arpa exact' => ['https://home.arpa', false];
        yield 'nested .test subdomain' => ['https://deep.sub.myshop.test', false];

        // Invalid URLs - Malformed
        yield 'Invalid URL' => ['not-a-url', false];
        yield 'Empty string' => ['', false];
        yield 'Only scheme' => ['https://', false];
    }
}
