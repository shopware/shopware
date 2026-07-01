<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;

/**
 * @internal
 */
#[CoversClass(ConfigCanonicalizer::class)]
class ConfigCanonicalizerTest extends TestCase
{
    private ConfigCanonicalizer $canonicalizer;

    protected function setUp(): void
    {
        $this->canonicalizer = new ConfigCanonicalizer();
    }

    #[TestDox('key-sorts a flat map so key order does not affect the canonical shape')]
    public function testKeySortsFlatMap(): void
    {
        static::assertSame(
            ['associations' => 'media', 'limit' => 5],
            $this->canonicalizer->canonicalize(['limit' => 5, 'associations' => 'media'])
        );
    }

    #[TestDox('value-sorts a list so list order does not affect the canonical shape')]
    public function testValueSortsListValues(): void
    {
        static::assertSame(
            ['associations' => ['manufacturer', 'media']],
            $this->canonicalizer->canonicalize(['associations' => ['media', 'manufacturer']])
        );
    }

    #[TestDox('recurses into a nested associative sub-array, key-sorting it too')]
    public function testRecursesIntoNestedAssociativeSubArray(): void
    {
        static::assertSame(
            ['filters' => ['limit' => 10, 'status' => 'active']],
            $this->canonicalizer->canonicalize(['filters' => ['status' => 'active', 'limit' => 10]])
        );
    }

    #[TestDox('produces the identical canonical shape for two configs differing only in key and list order')]
    public function testTwoConfigsDifferingOnlyInOrderCanonicalizeEqual(): void
    {
        $first = [
            'associations' => ['media', 'manufacturer'],
            'filters' => ['status' => 'active', 'limit' => 10],
        ];
        $second = [
            'filters' => ['limit' => 10, 'status' => 'active'],
            'associations' => ['manufacturer', 'media'],
        ];

        static::assertSame(
            $this->canonicalizer->canonicalize($first),
            $this->canonicalizer->canonicalize($second)
        );
    }

    #[TestDox('leaves an empty config unchanged')]
    public function testLeavesEmptyConfigUnchanged(): void
    {
        static::assertSame([], $this->canonicalizer->canonicalize([]));
    }
}
