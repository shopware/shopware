<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HmacSigner;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HmacSigner::class)]
class HmacSignerTest extends TestCase
{
    /**
     * RFC 4231, test case 2.
     */
    private const RFC_KEY = 'Jefe';
    private const RFC_DATA = 'what do ya want for nothing?';
    private const RFC_HEX = '5bdcc146bf60754e6a042426089575c75a003f089d2739839dec58b964ec3843';

    public function testSignsWithHmacSha256AsBase64UrlWithoutPadding(): void
    {
        $signer = new HmacSigner(self::RFC_KEY, new MockClock());

        static::assertSame('W9zBRr9gdU5qBCQmCJV1x1oAPwidJzmDnexYuWTsOEM', $signer->sign(self::RFC_DATA));
        static::assertTrue($signer->verify(self::RFC_DATA, 'W9zBRr9gdU5qBCQmCJV1x1oAPwidJzmDnexYuWTsOEM'));
    }

    public function testSignsWithHmacSha256AsHex(): void
    {
        $signer = new HmacSigner(self::RFC_KEY, new MockClock());

        static::assertSame(self::RFC_HEX, $signer->signHex(self::RFC_DATA));
        static::assertTrue($signer->verifyHex(self::RFC_DATA, self::RFC_HEX));
    }

    public function testRejectsASignatureForOtherData(): void
    {
        $signer = new HmacSigner(self::RFC_KEY, new MockClock());

        static::assertFalse($signer->verify('other data', $signer->sign(self::RFC_DATA)));
        static::assertFalse($signer->verifyHex('other data', $signer->signHex(self::RFC_DATA)));
    }

    public function testRejectsASignatureMadeWithAnotherSecret(): void
    {
        $clock = new MockClock();
        $other = new HmacSigner('other-secret', $clock);

        $signer = new HmacSigner(self::RFC_KEY, $clock);

        static::assertFalse($signer->verify(self::RFC_DATA, $other->sign(self::RFC_DATA)));
        static::assertFalse($signer->verifyHex(self::RFC_DATA, $other->signHex(self::RFC_DATA)));
    }

    public function testDoesNotAcceptTheOtherEncoding(): void
    {
        $signer = new HmacSigner(self::RFC_KEY, new MockClock());

        static::assertFalse($signer->verify(self::RFC_DATA, self::RFC_HEX));
        static::assertFalse($signer->verifyHex(self::RFC_DATA, $signer->sign(self::RFC_DATA)));
    }

    public function testExpiryIsCountedFromTheInjectedClockAtSecondPrecision(): void
    {
        $signer = new HmacSigner(self::RFC_KEY, new MockClock('2026-09-28T10:00:00.750+00:00'));

        static::assertSame('2026-09-28T11:00:00.000+00:00', $signer->expiresAt(3600)->format(\DateTimeInterface::RFC3339_EXTENDED));
    }

    public function testATokenStaysValidThroughItsExpirySecond(): void
    {
        $clock = new MockClock('2026-09-28T10:00:00+00:00');
        $signer = new HmacSigner(self::RFC_KEY, $clock);
        $expiresAt = $signer->expiresAt(60)->getTimestamp();

        $clock->sleep(60);
        static::assertFalse($signer->isExpired($expiresAt));

        $clock->sleep(1);
        static::assertTrue($signer->isExpired($expiresAt));
    }
}
