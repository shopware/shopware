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
#[Package('fundamentals@after-sales')]
#[CoversClass(DateRangeRule::class)]
class DateRangeRuleTest extends TestCase
{
    #[DataProvider('matchDataProvider')]
    public function testMatch(
        ?string $fromDate,
        ?string $toDate,
        bool $useTime,
        ?string $timezone,
        string $now,
        bool $expectedResult
    ): void {
        $rule = new DateRangeRule(
            $fromDate ? new \DateTime($fromDate) : null,
            $toDate ? new \DateTime($toDate) : null,
            $useTime,
            $timezone ? new \DateTimeZone($timezone) : null,
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
                '2021-01-01 00:00:00',
                '2021-01-01 00:00:00',
                false,
                null,
                '2021-01-01 00:00:00',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 00:00:00',
                false,
                null,
                '2020-12-31 23:59:59',
                false,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 00:00:00',
                false,
                null,
                '2021-01-01 23:59:59',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 00:00:00',
                false,
                null,
                '2021-01-02 00:00:00',
                false,
            ],
            [
                '2021-01-01 11:00:00',
                '2021-01-02 10:00:00',
                false,
                null,
                '2021-01-01 10:00:00',
                true,
            ],
            [
                '2021-01-01 11:00:00',
                '2021-01-02 10:00:00',
                false,
                null,
                '2021-01-02 10:00:00',
                true,
            ],
            [
                '2021-01-01 11:00:00',
                '2021-01-02 10:00:00',
                false,
                null,
                '2021-01-03 10:00:00',
                false,
            ],

            // from and to set, useTime = true
            [
                '2021-01-01 00:00:00',
                '2021-01-01 10:00:00',
                true,
                null,
                '2021-01-01 00:00:00',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 10:00:00',
                true,
                null,
                '2020-12-31 23:59:59',
                false,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 10:00:00',
                true,
                null,
                '2021-01-01 09:59:59',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 10:00:00',
                true,
                null,
                '2021-01-01 10:00:00',
                false,
            ],

            // only from set, useTime = false
            [
                '2021-01-01 00:00:00',
                null,
                false,
                null,
                '2021-01-01 00:00:00',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                null,
                false,
                null,
                '2020-12-31 23:59:59',
                false,
            ],

            // only from set, useTime = true
            [
                '2021-01-01 00:00:00',
                null,
                true,
                null,
                '2021-01-01 00:00:00',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                null,
                true,
                null,
                '2020-12-31 23:59:59',
                false,
            ],

            // only to set, useTime = false
            [
                null,
                '2021-01-01 00:00:00',
                false,
                null,
                '2021-01-01 23:59:59',
                true,
            ],
            [
                null,
                '2021-01-01 00:00:00',
                false,
                null,
                '2021-01-02 00:00:00',
                false,
            ],

            // Some timezone checks

            // with useTime = false
            [
                '2021-01-01 10:00:00',
                '2021-01-01 20:00:00',
                true,
                'UTC',
                '2021-01-01 20:00:00 -01:00',
                false,
            ],
            [
                '2021-01-01 10:00:00',
                '2021-01-01 20:00:00',
                true,
                'UTC',
                '2021-01-01 20:00:00 +01:00',
                true,
            ],
            [
                '2021-01-01 00:00:00',
                '2021-01-01 00:00:00',
                false,
                'UTC',
                '2021-01-02 02:00:00 +04:00',
                true,
            ],
            [
                '2021-01-02 00:00:00',
                '2021-01-02 00:00:00',
                false,
                'Etc/GMT-2',
                '2021-01-01 22:00:00',
                true,
            ],
            [
                '2021-01-02 00:00:00',
                '2021-01-02 00:00:00',
                false,
                'Etc/GMT-2',
                '2021-01-01 21:59:59',
                true,
            ],
            // with useTime = true
            [
                '2021-01-01 10:00:00',
                '2021-01-01 20:00:00',
                true,
                'Etc/GMT-2',
                '2021-01-01 08:00:00',
                true,
            ],
            [
                '2021-01-01 10:00:00',
                '2021-01-01 20:00:00',
                true,
                'Etc/GMT-2',
                '2021-01-01 07:59:59',
                false,
            ],

            // nothing set
            [
                null,
                null,
                true,
                null,
                '2021-01-01 07:59:59',
                true,
            ],
        ];
    }
}
