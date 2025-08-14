<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\FingerprintMatch;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(FingerprintGenerator::class)]
#[Package('framework')]
class FingerprintGeneratorTest extends TestCase
{
    private FingerprintGenerator $fingerprintGenerator;

    protected function setUp(): void
    {
        $this->fingerprintGenerator = new FingerprintGenerator([
            new FooFingerprint(),
            new BarFingerprint(),
            new BazFingerprint(),
        ]);
    }

    public function testTakesFingerprints(): void
    {
        $fingerprints = $this->fingerprintGenerator->takeFingerprints();

        static::assertArrayHasKey('foo', $fingerprints);
        static::assertSame('foo', $fingerprints['foo']);

        static::assertArrayHasKey('bar', $fingerprints);
        static::assertSame('bar', $fingerprints['bar']);

        static::assertArrayHasKey('baz', $fingerprints);
        static::assertSame('baz', $fingerprints['baz']);
    }

    /**
     * @param array<string, string> $fingerprints
     */
    #[DataProvider('fingerprintsForComparison')]
    public function testComparesFingerPrintAndReturnsResult(array $fingerprints, FingerprintComparisonResult $result): void
    {
        $fingerprintGenerator = new FingerprintGenerator([
            new FooFingerprint(),
            new BarFingerprint(),
            new BazFingerprint(),
        ]);

        static::assertEquals($result, $fingerprintGenerator->compare($fingerprints));
    }

    public static function fingerprintsForComparison(): \Generator
    {
        yield 'all match' => [
            [
                'foo' => 'foo',
                'bar' => 'bar',
                'baz' => 'baz',
            ],
            new FingerprintComparisonResult(
                [
                    'foo' => new FingerprintMatch('foo', 'foo'),
                    'bar' => new FingerprintMatch('bar', 'bar'),
                    'baz' => new FingerprintMatch('baz', 'baz'),
                ],
                [],
                75,
            ),
        ];

        yield 'one mismatch' => [
            [
                'foo' => 'foo',
                'bar' => 'wrong',
                'baz' => 'baz',
            ],
            new FingerprintComparisonResult(
                [
                    'foo' => new FingerprintMatch('foo', 'foo'),
                    'baz' => new FingerprintMatch('baz', 'baz'),
                ],
                [
                    'bar' => new FingerprintMismatch('bar', 'wrong', 'bar', 50),
                ],
                75,
            ),
        ];

        yield 'all mismatch' => [
            [
                'foo' => 'wrong',
                'bar' => 'wrong',
                'baz' => 'wrong',
            ],
            new FingerprintComparisonResult(
                [],
                [
                    'foo' => new FingerprintMismatch('foo', 'wrong', 'foo', 100),
                    'bar' => new FingerprintMismatch('bar', 'wrong', 'bar', 50),
                    'baz' => new FingerprintMismatch('baz', 'wrong', 'baz', 25),
                ],
                75,
            ),
        ];
    }
}

/**
 * @internal
 */
class FooFingerprint implements Fingerprint
{
    public function getIdentifier(): string
    {
        return 'foo';
    }

    public function getScore(): int
    {
        return 100;
    }

    public function getStamp(): string
    {
        return 'foo';
    }
}

/**
 * @internal
 */
class BarFingerprint implements Fingerprint
{
    public function getIdentifier(): string
    {
        return 'bar';
    }

    public function getScore(): int
    {
        return 50;
    }

    public function getStamp(): string
    {
        return 'bar';
    }
}

/**
 * @internal
 */
class BazFingerprint implements Fingerprint
{
    public function getIdentifier(): string
    {
        return 'baz';
    }

    public function getScore(): int
    {
        return 25;
    }

    public function getStamp(): string
    {
        return 'baz';
    }
}
