<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Service\CustomerNumberRangePatternMatcher;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CustomerNumberRangePatternMatcher::class)]
class CustomerNumberRangePatternMatcherTest extends TestCase
{
    #[DataProvider('customerNumberPatterns')]
    public function testExtractsIncrementFromCustomerNumberPattern(
        string $pattern,
        string $customerNumber,
        int $expectedIncrement,
    ): void {
        $matcher = new CustomerNumberRangePatternMatcher();

        static::assertSame($expectedIncrement, $matcher->extractIncrement($pattern, $customerNumber));
    }

    /**
     * @return iterable<string, array{pattern: string, customerNumber: string, expectedIncrement: int}>
     */
    public static function customerNumberPatterns(): iterable
    {
        yield 'plain increment' => [
            'pattern' => '{n}',
            'customerNumber' => '100014',
            'expectedIncrement' => 100014,
        ];

        yield 'prefix and suffix' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'customerNumber' => 'CUSTOMER-100014-EU',
            'expectedIncrement' => 100014,
        ];

        yield 'increment before default date' => [
            'pattern' => '{n}-{date}',
            'customerNumber' => '100014-2026-08-12',
            'expectedIncrement' => 100014,
        ];

        yield 'default date before increment' => [
            'pattern' => '{date}_{n}',
            'customerNumber' => '2026-08-12_100014',
            'expectedIncrement' => 100014,
        ];

        yield 'custom date format' => [
            'pattern' => 'DOC-{date_d.m.Y}/{n}',
            'customerNumber' => 'DOC-12.08.2026/100014',
            'expectedIncrement' => 100014,
        ];

        yield 'whitespace and special characters' => [
            'pattern' => 'Customer / {n} (EU) #1',
            'customerNumber' => 'Customer / 100014 (EU) #1',
            'expectedIncrement' => 100014,
        ];

        yield 'increment directly before date' => [
            'pattern' => 'PREFIX-{n}{date_Ymd}',
            'customerNumber' => 'PREFIX-10001420260812',
            'expectedIncrement' => 100014,
        ];

        yield 'date directly before increment' => [
            'pattern' => 'PREFIX-{date_Ymd}{n}',
            'customerNumber' => 'PREFIX-20260812100014',
            'expectedIncrement' => 100014,
        ];

        yield 'ambiguous literal and placeholder adjacency' => [
            'pattern' => 'PREFIX-99{n}12{date_Ymd}42',
            'customerNumber' => 'PREFIX-99100014122026081242',
            'expectedIncrement' => 100014,
        ];
    }

    #[DataProvider('invalidCustomerNumberPatterns')]
    public function testReturnsNullForInvalidCustomerNumberPattern(
        string $pattern,
        string $customerNumber,
    ): void {
        $matcher = new CustomerNumberRangePatternMatcher();

        static::assertNull($matcher->extractIncrement($pattern, $customerNumber));
    }

    /**
     * @return iterable<string, array{pattern: string, customerNumber: string}>
     */
    public static function invalidCustomerNumberPatterns(): iterable
    {
        yield 'literal suffix does not match' => [
            'pattern' => 'CUSTOMER-{n}-EU',
            'customerNumber' => 'CUSTOMER-100014-DE',
        ];

        yield 'missing increment value' => [
            'pattern' => 'CUSTOMER-{n}',
            'customerNumber' => 'CUSTOMER-ABC',
        ];

        yield 'invalid date' => [
            'pattern' => '{date}_{n}',
            'customerNumber' => '2026-99-99_100014',
        ];

        yield 'missing increment placeholder' => [
            'pattern' => 'CUSTOMER-{date}',
            'customerNumber' => 'CUSTOMER-2026-08-12',
        ];

        yield 'unknown placeholder' => [
            'pattern' => 'CUSTOMER-{external-value}-{n}',
            'customerNumber' => 'CUSTOMER-value-100014',
        ];
    }
}
