<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TrustedUrlResolver::class)]
class TrustedUrlResolverTest extends TestCase
{
    public function testResolveReturnsValidatedAddressForPinning(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']);

        $resolved = $resolver->resolve('https://media.example.com/image.png');

        static::assertSame('media.example.com', $resolved->host);
        static::assertSame('93.184.216.34', $resolved->ip);
    }

    public function testResolveRejectsAaaaOnlyPrivateHost(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['fd00::1']);

        $this->expectExceptionObject(MediaException::illegalUrl('https://ipv6.example.com/image.png'));

        $resolver->resolve('https://ipv6.example.com/image.png');
    }

    public function testResolveRejectsWhenAnyRecordIsPrivate(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34', '10.0.0.5']);

        $this->expectException(MediaException::class);

        $resolver->resolve('https://mixed.example.com/image.png');
    }

    public function testResolveRejectsUnresolvableHost(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => []);

        $this->expectException(MediaException::class);

        $resolver->resolve('https://does-not-resolve.example.com/image.png');
    }

    #[DataProvider('blockedProvider')]
    public function testResolveRejectsBlockedTargets(string $url, string $resolvedIp): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => [$resolvedIp]);

        $this->expectException(MediaException::class);

        $resolver->resolve($url);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function blockedProvider(): iterable
    {
        yield 'aws/gcp/azure metadata' => ['https://metadata.example.com/x', '169.254.169.254'];
        yield 'aws ecs metadata' => ['https://metadata.example.com/x', '169.254.170.2'];
        yield 'alibaba metadata' => ['https://metadata.example.com/x', '100.100.100.200'];
        yield 'oracle metadata' => ['https://metadata.example.com/x', '192.0.0.192'];
        yield 'loopback' => ['https://local.example.com/x', '127.0.0.1'];
        yield 'rfc1918' => ['https://internal.example.com/x', '192.168.1.10'];
    }

    #[DataProvider('schemeProvider')]
    public function testResolveRejectsNonHttpSchemes(string $url): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']);

        $this->expectException(MediaException::class);

        $resolver->resolve($url);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function schemeProvider(): iterable
    {
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com/x'];
        yield 'gopher' => ['gopher://example.com/x'];
        yield 'no scheme' => ['example.com/x'];
    }

    public function testResolveValidatesPublicIpLiteralWithoutDns(): void
    {
        $called = false;
        $resolver = new TrustedUrlResolver(static function () use (&$called): array {
            $called = true;

            return [];
        });

        $resolved = $resolver->resolve('https://8.8.8.8/image.png');

        static::assertSame('8.8.8.8', $resolved->ip);
        static::assertFalse($called, 'An IP literal must not be sent through the DNS resolver');
    }

    public function testResolveRejectsPrivateIpLiteral(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => []);

        $this->expectException(MediaException::class);

        $resolver->resolve('https://127.0.0.1/image.png');
    }

    public function testIsValidMirrorsResolve(): void
    {
        $resolver = new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']);
        static::assertTrue($resolver->isValid('https://media.example.com/image.png'));

        $blocking = new TrustedUrlResolver(static fn (string $host): array => ['10.0.0.1']);
        static::assertFalse($blocking->isValid('https://media.example.com/image.png'));
    }

    public function testPermittingPrivateRangesStillPinsTheResolvedAddress(): void
    {
        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => ['127.0.0.1'],
            rejectPrivateRanges: false,
        );

        $resolved = $resolver->resolve('http://localhost:8000/image.png');

        static::assertSame('127.0.0.1', $resolved->ip);
    }

    public function testAllowsExactPrivateIpWhenAllowListed(): void
    {
        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => ['10.0.0.10'],
            allowedPrivateIps: ['10.0.0.10'],
        );

        $resolved = $resolver->resolve('https://internal.example.com/image.png');

        static::assertSame('10.0.0.10', $resolved->ip);
    }

    public function testRejectsPrivateIpWhenDifferentPrivateIpIsAllowListed(): void
    {
        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => ['10.0.0.11'],
            allowedPrivateIps: ['10.0.0.10'],
        );

        $this->expectException(MediaException::class);

        $resolver->resolve('https://internal.example.com/image.png');
    }

    public function testPermittingPrivateRangesStillRejectsUnusableUrls(): void
    {
        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => [],
            rejectPrivateRanges: false,
        );

        $this->expectException(MediaException::class);

        $resolver->resolve('http://does-not-resolve.example.com/image.png');
    }

    public function testResolutionIsMemoisedPerHostAndClearedOnReset(): void
    {
        $counter = new class {
            public int $calls = 0;
        };

        $resolver = new TrustedUrlResolver(static function () use ($counter): array {
            ++$counter->calls;

            return ['93.184.216.34'];
        });

        $resolver->resolve('https://media.example.com/a.png');
        $resolver->resolve('https://media.example.com/b.png');
        static::assertSame(1, $counter->calls);

        $resolver->reset();
        $resolver->resolve('https://media.example.com/c.png');
        static::assertSame(2, $counter->calls);
    }

    public function testFallsBackToHostsFileWhenDnsReturnsNothing(): void
    {
        // no stub: exercises the real hosts-file fallback
        $resolver = new TrustedUrlResolver(rejectPrivateRanges: false);

        $resolved = $resolver->resolve('http://localhost/image.png');

        static::assertNotSame('', $resolved->ip);
        static::assertNotFalse(filter_var($resolved->ip, \FILTER_VALIDATE_IP));
    }
}
