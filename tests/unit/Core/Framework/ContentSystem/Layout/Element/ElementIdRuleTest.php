<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ElementIdRejection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ElementIdRule;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;

/**
 * The verdict only — each enforcement site words it and pins its own text. Agreement with the published
 * pattern is `ElementIdSchemaConformanceTest`'s job.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementIdRule::class)]
class ElementIdRuleTest extends TestCase
{
    #[DataProvider('rejectionProvider')]
    #[TestDox('$_dataName')]
    public function testRejection(string $id, ?ElementIdRejection $expected): void
    {
        static::assertSame($expected, ElementIdRule::rejection($id));
    }

    /**
     * @return iterable<string, array{string, ElementIdRejection|null}>
     */
    public static function rejectionProvider(): iterable
    {
        yield 'admits an author-supplied id' => ['hero', null];

        yield 'admits a server-minted hex id' => ['0188fa1b2c3d4e5f6a7b8c9d0e1f2a3b', null];

        yield 'admits a leading-zero digit string, which PHP keeps as a string key' => ['012', null];

        yield 'admits the empty string, which the write descriptor refuses through NotBlank instead' => ['', null];

        yield 'refuses the reserved virtual-root literal' => [
            VirtualRootWrapper::VIRTUAL_ROOT_ID,
            ElementIdRejection::ReservedLiteral,
        ];

        yield 'refuses an integer-castable id' => [
            '12',
            ElementIdRejection::IntegerLiteral,
        ];

        yield 'refuses a negative zero, which PHP alone would have kept as a string key' => [
            '-0',
            ElementIdRejection::IntegerLiteral,
        ];

        yield 'refuses a digit string past PHP_INT_MAX, likewise' => [
            '9223372036854775808',
            ElementIdRejection::IntegerLiteral,
        ];

        yield 'admits a non-canonical digit string, so no minted hex id can collide' => ['00', null];

        yield 'refuses a line feed' => ['hero' . "\n", ElementIdRejection::LineTerminator];

        yield 'refuses a carriage return' => ['hero' . "\r", ElementIdRejection::LineTerminator];

        yield 'refuses a line separator' => ['hero' . "\u{2028}", ElementIdRejection::LineTerminator];

        yield 'refuses a paragraph separator' => ['hero' . "\u{2029}", ElementIdRejection::LineTerminator];

        // Pins that the rule means ECMA-262's LineTerminator set and not Unicode's wider one, so nobody
        // widens it to `\s` while tidying.
        yield 'admits NEL, which ECMA-262 does not count as a line terminator' => ['hero' . "\u{0085}", null];

        yield 'admits a vertical tab' => ['hero' . "\u{000B}", null];

        yield 'admits a form feed' => ['hero' . "\u{000C}", null];

        yield 'admits a tab' => ["hero\tfoot", null];
    }
}
