<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AssertEqualsCanonicalizingListArgumentRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class Cases extends TestCase
{
    public function testListArgumentsAreAllowed(): void
    {
        $a = 'a';
        $b = 'b';
        $keyed = ['x' => 'x'];

        // both list literals
        static::assertEqualsCanonicalizing([$a, $b], [$b, $a]);
        // list literal + array_values()
        static::assertEqualsCanonicalizing([$a, $b], array_values($keyed));
        // array_keys() + array_column()
        static::assertEqualsCanonicalizing(array_keys($keyed), array_column([], 'id'));
    }

    public function testNonListArgumentsAreFlagged(): void
    {
        $a = 'a';
        $keyed = ['x' => 'x'];

        // arg #2 is a bare variable (not provably a list)
        static::assertEqualsCanonicalizing([$a], $keyed);
        // arg #1 is a bare variable
        static::assertEqualsCanonicalizing($keyed, [$a]);
        // arg #2 is a keyed array literal
        static::assertEqualsCanonicalizing([$a], ['x' => 'x']);
    }

    public function testTypeProvenListVariablesAreAllowed(): void
    {
        $a = 'a';
        $b = 'b';

        // bare variables, but PHPStan infers list<string> for both -> isList()->yes()
        $left = [$a, $b];
        $right = [$b, $a];
        static::assertEqualsCanonicalizing($left, $right);
    }
}
