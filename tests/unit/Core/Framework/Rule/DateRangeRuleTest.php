<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(DateRangeRule::class)]
class DateRangeRuleTest extends TestCase
{
    #[DataProvider('matchDataProvider')]
    public function testMatch(
        ?string $fromDate,
        ?string $toDate,
        bool $useTime,
        string $now,
        bool $expectedResult
    ): void {
        $rule = new DateRangeRule(
            $fromDate ? new \DateTime($fromDate) : null,
            $toDate ? new \DateTime($toDate) : null,
            $useTime
        );
        $scopeMock = $this->createMock(RuleScope::class);
        $scopeMock->method('getCurrentTime')->willReturn(new \DateTimeImmutable($now));

        $matchResult = $rule->match($scopeMock);

        static::assertSame($expectedResult, $matchResult);
    }

    /**
     * @return array<int, array<int, bool|string|null>>
     */
    public static function matchDataProvider(): array
    {
        return [
            // from and to set, useTime = false
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-01 00:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 00:00:00 UTC',
                false,
                '2020-12-31 23:59:59 UTC',
                false,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-01 23:59:59 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-02 00:00:00 UTC',
                false,
            ],
            [
                '2021-01-01 11:00:00 UTC',
                '2021-01-02 10:00:00 UTC',
                false,
                '2021-01-01 10:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 11:00:00 UTC',
                '2021-01-02 10:00:00 UTC',
                false,
                '2021-01-02 10:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 11:00:00 UTC',
                '2021-01-02 10:00:00 UTC',
                false,
                '2021-01-03 10:00:00 UTC',
                false,
            ],

            // from and to set, useTime = true
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 10:00:00 UTC',
                true,
                '2021-01-01 00:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 10:00:00 UTC',
                true,
                '2020-12-31 23:59:59 UTC',
                false,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 10:00:00 UTC',
                true,
                '2021-01-01 09:59:59 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 10:00:00 UTC',
                true,
                '2021-01-01 10:00:00 UTC',
                false,
            ],

            // only from set, useTime = false
            [
                '2021-01-01 00:00:00 UTC',
                null,
                false,
                '2021-01-01 00:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                null,
                false,
                '2020-12-31 23:59:59 UTC',
                false,
            ],

            // only from set, useTime = true
            [
                '2021-01-01 00:00:00 UTC',
                null,
                true,
                '2021-01-01 00:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                null,
                true,
                '2020-12-31 23:59:59 UTC',
                false,
            ],

            // only to set, useTime = false
            [
                null,
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-01 23:59:59 UTC',
                true,
            ],
            [
                null,
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-02 00:00:00 UTC',
                false,
            ],

            // Some timezone checks

            // with useTime = false
            [
                '2021-01-01 10:00:00 UTC',
                '2021-01-01 20:00:00 UTC',
                true,
                '2021-01-01 20:00:00 -01:00',
                false,
            ],
            [
                '2021-01-01 10:00:00 UTC',
                '2021-01-01 20:00:00 UTC',
                true,
                '2021-01-01 20:00:00 +01:00',
                true,
            ],
            [
                '2021-01-01 00:00:00 UTC',
                '2021-01-01 00:00:00 UTC',
                false,
                '2021-01-02 02:00:00 +04:00',
                true,
            ],
            [
                '2021-01-02 00:00:00 +02:00',
                '2021-01-02 00:00:00 +02:00',
                false,
                '2021-01-01 22:00:00 UTC',
                true,
            ],
            [
                '2021-01-02 00:00:00 +02:00',
                '2021-01-02 00:00:00 +02:00',
                false,
                '2021-01-01 21:59:59 UTC',
                true,
            ],
            // with useTime = true
            [
                '2021-01-01 10:00:00 +02:00',
                '2021-01-01 20:00:00 +02:00',
                true,
                '2021-01-01 08:00:00 UTC',
                true,
            ],
            [
                '2021-01-01 10:00:00 +02:00',
                '2021-01-01 20:00:00 +02:00',
                true,
                '2021-01-01 07:59:59 UTC',
                false,
            ],

            // nothing set
            [
                null,
                null,
                true,
                '2021-01-01 07:59:59 UTC',
                true,
            ],
        ];
    }
}
