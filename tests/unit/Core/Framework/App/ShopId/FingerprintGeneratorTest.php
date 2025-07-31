<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
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

    public function testDoesNotSuggestShopIdChangeIfAllFingerprintsMatch(): void
    {
        $fingerprints = [
            'foo' => 'foo',
            'bar' => 'bar',
            'baz' => 'baz',
        ];

        $this->fingerprintGenerator->compare($fingerprints);

        static::expectNotToPerformAssertions();
    }

    public function testDoesNotSuggestShopIdChangeIfScoreIsBelowThreshold(): void
    {
        $fingerprints = [
            'foo' => 'foo',
            'bar' => 'rab',
            'baz' => 'baz',
        ];

        $this->fingerprintGenerator->compare($fingerprints);

        static::expectNotToPerformAssertions();
    }

    public function testSuggestsShopIdChangeIfScoreIsEqualToThreshold(): void
    {
        $fingerprints = [
            'foo' => 'foo',
            'bar' => 'rab',
            'baz' => 'zab',
        ];

        try {
            $this->fingerprintGenerator->compare($fingerprints);

            static::fail(\sprintf('Expected "%s" to be thrown.', ShopIdChangeSuggestedException::class));
        } catch (ShopIdChangeSuggestedException $e) {
            static::assertSame([
                'bar',
                'baz',
            ], $e->mismatchingFingerprints);
        }
    }

    public function testSuggestsShopIdChangeIfScoreIsAboveThreshold(): void
    {
        $fingerprints = [
            'foo' => 'oof',
            'bar' => 'rab',
            'baz' => 'zab',
        ];

        try {
            $this->fingerprintGenerator->compare($fingerprints);

            static::fail(\sprintf('Expected "%s" to be thrown.', ShopIdChangeSuggestedException::class));
        } catch (ShopIdChangeSuggestedException $e) {
            static::assertSame([
                'foo',
                'bar',
                'baz',
            ], $e->mismatchingFingerprints);
        }
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
