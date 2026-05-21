<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Transport\Signature\ContentDigestCalculator;

/**
 * @internal
 */
#[CoversClass(ContentDigestCalculator::class)]
class ContentDigestCalculatorTest extends TestCase
{
    public function testSha256DigestMatchesExpectedFormat(): void
    {
        $calc = new ContentDigestCalculator();
        $digest = $calc->calculate('{"a":1}');
        static::assertMatchesRegularExpression('/^sha-256=:[A-Za-z0-9+\/=]+:$/', $digest);
    }

    public function testVerifyAcceptsCorrectDigest(): void
    {
        $calc = new ContentDigestCalculator();
        $body = '{"a":1}';
        $digest = $calc->calculate($body);
        static::assertTrue($calc->verify($body, $digest));
    }

    public function testVerifyRejectsTamperedBody(): void
    {
        $calc = new ContentDigestCalculator();
        $digest = $calc->calculate('original');
        static::assertFalse($calc->verify('tampered', $digest));
    }

    public function testVerifyAcceptsSha512(): void
    {
        $calc = new ContentDigestCalculator();
        $body = 'hello';
        $digest = $calc->calculate($body, ContentDigestCalculator::ALGO_SHA512);
        static::assertTrue($calc->verify($body, $digest));
    }

    public function testVerifyRejectsMalformedHeader(): void
    {
        $calc = new ContentDigestCalculator();
        static::assertFalse($calc->verify('body', ''));
        static::assertFalse($calc->verify('body', 'not-a-digest'));
        static::assertFalse($calc->verify('body', 'sha-1=:abc:'));
    }
}
