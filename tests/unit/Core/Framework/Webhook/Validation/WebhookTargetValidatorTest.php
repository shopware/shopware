<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
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
        $validator = new WebhookTargetValidator(false, [], new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']));

        $target = $validator->validate('https://example.com/webhook');

        static::assertNotNull($target);
        static::assertSame('example.com', $target->host);
        static::assertSame(443, $target->port);
        static::assertSame('93.184.216.34', $target->ip);
    }

    public function testHttpTargetIsRejectedByDefault(): void
    {
        $validator = new WebhookTargetValidator(false, [], new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']));

        static::assertNull($validator->validate('http://example.com/webhook'));
    }

    public function testHttpTargetCanBeAllowedExplicitly(): void
    {
        $validator = new WebhookTargetValidator(true, [], new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']));

        $target = $validator->validate('http://example.com/webhook');

        static::assertNotNull($target);
        static::assertSame(80, $target->port);
    }

    /**
     * @param list<string> $records
     */
    #[DataProvider('rejectedDnsRecords')]
    public function testRejectsAnyNonPublicDnsRecord(array $records): void
    {
        $validator = new WebhookTargetValidator(false, [], new TrustedUrlResolver(static fn (string $host): array => $records));

        static::assertNull($validator->validate('https://example.com/webhook'));
    }

    public function testAllowsConfiguredInternalDnsRecord(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], new TrustedUrlResolver(static fn (string $host): array => ['10.0.0.10'], allowedPrivateIps: ['10.0.0.10']));

        $target = $validator->validate('https://internal.example.com/webhook');

        static::assertNotNull($target);
        static::assertSame('10.0.0.10', $target->ip);
    }

    public function testRejectsIpLiteralUnlessAllowListed(): void
    {
        $validator = new WebhookTargetValidator(false, [], new TrustedUrlResolver(static fn (string $host): array => []));

        static::assertNull($validator->validate('https://93.184.216.34/webhook'));
    }

    public function testRejectsMalformedUrl(): void
    {
        $validator = new WebhookTargetValidator(false, [], new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34']));

        static::assertNull($validator->validate('https://example.com:invalid/webhook'));
        static::assertNull($validator->validate('https://example.com:99999/webhook'));
    }

    public function testAllowsConfiguredIpLiteral(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], new TrustedUrlResolver(static fn (string $host): array => [], allowedPrivateIps: ['10.0.0.10']));

        $target = $validator->validate('https://10.0.0.10/webhook');

        static::assertNotNull($target);
        static::assertSame('10.0.0.10', $target->host);
        static::assertSame('10.0.0.10', $target->ip);
    }

    public function testRejectsHostWhenAnyRecordIsNotAllowed(): void
    {
        $validator = new WebhookTargetValidator(false, ['10.0.0.10'], new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34', '10.0.0.11'], allowedPrivateIps: ['10.0.0.10']));

        static::assertNull($validator->validate('https://example.com/webhook'));
    }

    /**
     * @return \Generator<string, array{records: list<string>}>
     */
    public static function rejectedDnsRecords(): \Generator
    {
        yield 'private IPv4' => ['records' => ['10.0.0.10']];
        yield 'loopback IPv4' => ['records' => ['127.0.0.1']];
        yield 'link-local IPv4' => ['records' => ['169.254.169.254']];
        yield 'private IPv6' => ['records' => ['fc00::1']];
        yield 'loopback IPv6' => ['records' => ['::1']];
        yield 'IPv4-mapped IPv6 loopback' => ['records' => ['::ffff:127.0.0.1']];
        yield 'mixed public and private records' => ['records' => ['93.184.216.34', '10.0.0.10']];
        yield 'no records' => ['records' => []];
    }
}
