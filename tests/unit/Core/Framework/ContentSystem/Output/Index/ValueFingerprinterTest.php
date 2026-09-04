<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ValueFingerprinter::class)]
class ValueFingerprinterTest extends TestCase
{
    #[TestDox('fingerprints an object as its instance id')]
    public function testObjectFingerprintIsItsInstanceId(): void
    {
        $value = new StubStruct();

        static::assertSame((string) spl_object_id($value), (new ValueFingerprinter())->fingerprint($value));
    }

    #[TestDox('fingerprints equal scalars identically across separate calls')]
    public function testEqualScalarsFingerprintIdentically(): void
    {
        $fingerprinter = new ValueFingerprinter();

        static::assertSame($fingerprinter->fingerprint('Hello'), $fingerprinter->fingerprint('Hello'));
    }

    /**
     * Two objects that are equal in every field are still two values as far as this rule is concerned, which
     * is what lets the index tell a replaced value from the one a loader returned.
     *
     * Both instances are held in variables on purpose. `spl_object_id` is only unique among objects that are
     * alive at the same time, and passing two temporaries here would hand the second one the id the first
     * released — which is the recycling hazard the class documents, not the property under test. The values
     * this rule fingerprints in production are alive together too: the rendered tree holds them.
     */
    #[TestDox('fingerprints distinct instances differently even when their contents are equal')]
    public function testDistinctInstancesFingerprintDifferently(): void
    {
        $first = new StubStruct();
        $second = new StubStruct();
        $fingerprinter = new ValueFingerprinter();

        static::assertNotSame($fingerprinter->fingerprint($first), $fingerprinter->fingerprint($second));
    }

    #[TestDox('fingerprints different scalars differently')]
    public function testDifferentScalarsFingerprintDifferently(): void
    {
        $fingerprinter = new ValueFingerprinter();

        static::assertNotSame($fingerprinter->fingerprint('Hello'), $fingerprinter->fingerprint('Goodbye'));
    }

    /**
     * A loader's `notFound()` records a fingerprint like any other produced value, so null needs one that
     * still matches when it is recomputed at finalization.
     */
    #[TestDox('fingerprints null to a stable value that no scalar shares')]
    public function testNullFingerprintIsStable(): void
    {
        $fingerprinter = new ValueFingerprinter();

        static::assertSame($fingerprinter->fingerprint(null), $fingerprinter->fingerprint(null));
        static::assertNotSame($fingerprinter->fingerprint(null), $fingerprinter->fingerprint(''));
    }
}
