<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookTargetValidator::class)]
class WebhookTargetValidatorTest extends TestCase
{
    public function testValidHttpsTargetReturnsResolvedAddressForPinning(): void
    {
        $validator = new WebhookTargetValidator(false, [], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
        ]);

        $target = $validator->validate('https://example.com/webhook');

        static::assertNotNull($target);
        static::assertSame('example.com', $target->host);
        static::assertSame(443, $target->port);
        static::assertSame('93.184.216.34', $target->ip);
    }

    public function testHttpTargetIsRejectedByDefault(): void
    {
        $validator = new WebhookTargetValidator(false, [], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
        ]);

        static::assertNull($validator->validate('http://example.com/webhook'));
    }

    public function testHttpTargetCanBeAllowedExplicitly(): void
    {
        $validator = new WebhookTargetValidator(true, [], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
        ]);

        $target = $validator->validate('http://example.com/webhook');

        static::assertNotNull($target);
        static::assertSame(80, $target->port);
    }

    /**
     * @param list<array{ip?: string, ipv6?: string}> $records
     */
    #[DataProvider('rejectedDnsRecords')]
    public function testRejectsAnyNonPublicDnsRecord(array $records): void
    {
        $validator = new WebhookTargetValidator(false, [], static fn (string $host): array => $records);

        static::assertNull($validator->validate('https://example.com/webhook'));
    }

    public function testAllowsConfiguredInternalDnsRecord(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], static fn (string $host): array => [
            ['ip' => '10.0.0.10'],
        ]);

        $target = $validator->validate('https://internal.example.com/webhook');

        static::assertNotNull($target);
        static::assertSame('10.0.0.10', $target->ip);
    }

    public function testRejectsIpLiteralUnlessAllowListed(): void
    {
        $validator = new WebhookTargetValidator(false, [], static fn (string $host): array => []);

        static::assertNull($validator->validate('https://93.184.216.34/webhook'));
    }

    public function testRejectsMalformedUrl(): void
    {
        $validator = new WebhookTargetValidator(false, [], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
        ]);

        static::assertNull($validator->validate('https://example.com:invalid/webhook'));
        static::assertNull($validator->validate('https://example.com:99999/webhook'));
    }

    public function testAllowsConfiguredIpLiteral(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], static fn (string $host): array => []);

        $target = $validator->validate('https://10.0.0.10/webhook');

        static::assertNotNull($target);
        static::assertSame('10.0.0.10', $target->host);
        static::assertSame('10.0.0.10', $target->ip);
    }

    public function testRejectsHostWhenAnyRecordIsNotAllowed(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
            ['ip' => '10.0.0.11'],
        ]);

        static::assertNull($validator->validate('https://example.com/webhook'));
    }

    /**
     * @return \Generator<string, array{records: list<array{ip?: string, ipv6?: string}>}>
     */
    public static function rejectedDnsRecords(): \Generator
    {
        yield 'private IPv4' => ['records' => [['ip' => '10.0.0.10']]];
        yield 'this network IPv4' => ['records' => [['ip' => '0.0.0.1']]];
        yield 'carrier-grade NAT IPv4' => ['records' => [['ip' => '100.64.0.1']]];
        yield 'loopback IPv4' => ['records' => [['ip' => '127.0.0.1']]];
        yield 'link-local IPv4' => ['records' => [['ip' => '169.254.169.254']]];
        yield 'IETF protocol assignment IPv4' => ['records' => [['ip' => '192.0.0.1']]];
        yield 'documentation IPv4' => ['records' => [['ip' => '192.0.2.1']]];
        yield 'benchmarking IPv4' => ['records' => [['ip' => '198.18.0.1']]];
        yield 'documentation IPv4 range two' => ['records' => [['ip' => '198.51.100.1']]];
        yield 'documentation IPv4 range three' => ['records' => [['ip' => '203.0.113.1']]];
        yield 'multicast IPv4' => ['records' => [['ip' => '224.0.0.1']]];
        yield 'reserved IPv4' => ['records' => [['ip' => '240.0.0.1']]];
        yield 'private IPv6' => ['records' => [['ipv6' => 'fc00::1']]];
        yield 'IPv4-compatible IPv6 loopback' => ['records' => [['ipv6' => '::127.0.0.1']]];
        yield 'loopback IPv6' => ['records' => [['ipv6' => '::1']]];
        yield 'IPv4-mapped IPv6 loopback' => ['records' => [['ipv6' => '::ffff:127.0.0.1']]];
        yield 'well-known NAT64 IPv6' => ['records' => [['ipv6' => '64:ff9b::7f00:1']]];
        yield 'dummy IPv6' => ['records' => [['ipv6' => '100:0:0:1::1']]];
        yield 'documentation IPv6' => ['records' => [['ipv6' => '2001:db8::1']]];
        yield 'IETF protocol assignment IPv6' => ['records' => [['ipv6' => '2001::1']]];
        yield '6to4 IPv6' => ['records' => [['ipv6' => '2002::1']]];
        yield 'segment-routing IPv6' => ['records' => [['ipv6' => '5f00::1']]];
        yield 'site-local IPv6' => ['records' => [['ipv6' => 'fec0::1']]];
        yield 'multicast IPv6' => ['records' => [['ipv6' => 'ff02::1']]];
        yield 'mixed public and private records' => ['records' => [['ip' => '93.184.216.34'], ['ip' => '10.0.0.10']]];
        yield 'no records' => ['records' => []];
    }
}
