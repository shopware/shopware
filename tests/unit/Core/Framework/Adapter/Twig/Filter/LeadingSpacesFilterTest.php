<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Filter\LeadingSpacesFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LeadingSpacesFilter::class)]
class LeadingSpacesFilterTest extends TestCase
{
    #[DataProvider('removeLeadingSpacesProvider')]
    public function testRemoveLeadingSpacesFilter(string $input, string $expected): void
    {
        $filter = new LeadingSpacesFilter();
        static::assertSame($expected, $filter->removeLeadingSpaces($input));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function removeLeadingSpacesProvider(): iterable
    {
        yield 'single line with leading spaces' => [
            ' test',
            'test',
        ];
        yield 'single line with leading and trailing spaces' => [
            ' test ',
            'test',
        ];
        yield 'multiple leading spaces' => [
            '     test',
            'test',
        ];
        yield 'multiline with leading spaces' => [
            "  line1\n   line2\n    line3  ",
            "line1\nline2\nline3",
        ];
        yield 'multiline with mixed spaces and empty lines' => [
            "  line1\n\n   line2\n     line3",
            "line1\n\nline2\nline3",
        ];
        yield 'multiline with tabs and spaces' => [
            "\tline1\n  line2\n\t  line3",
            "line1\nline2\nline3",
        ];
        yield 'empty string' => [
            '',
            '',
        ];
        yield 'only spaces' => [
            '   ',
            '',
        ];
    }
}
