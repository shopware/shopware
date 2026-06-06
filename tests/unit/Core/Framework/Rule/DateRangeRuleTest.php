<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Test\Assert\Serialization;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

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
     * @return iterable<string, array<int, bool|string|null>>
     */
    public static function matchDataProvider(): iterable
    {
        yield 'same day range without time matches the start of the day' => [
            '2021-01-01 00:00:00',
            '2021-01-01 00:00:00',
            false,
            null,
            '2021-01-01 00:00:00',
            true,
        ];
        yield 'same day range without time rejects the previous second' => [
            '2021-01-01 00:00:00',
            '2021-01-01 00:00:00',
            false,
            null,
            '2020-12-31 23:59:59',
            false,
        ];
        yield 'same day range without time includes the end of the day' => [
            '2021-01-01 00:00:00',
            '2021-01-01 00:00:00',
            false,
            null,
            '2021-01-01 23:59:59',
            true,
        ];
        yield 'same day range without time rejects the next day' => [
            '2021-01-01 00:00:00',
            '2021-01-01 00:00:00',
            false,
            null,
            '2021-01-02 00:00:00',
            false,
        ];
        yield 'multi day range without time includes the start day' => [
            '2021-01-01 11:00:00',
            '2021-01-02 10:00:00',
            false,
            null,
            '2021-01-01 10:00:00',
            true,
        ];
        yield 'multi day range without time includes the end day' => [
            '2021-01-01 11:00:00',
            '2021-01-02 10:00:00',
            false,
            null,
            '2021-01-02 10:00:00',
            true,
        ];
        yield 'multi day range without time rejects the day after the end' => [
            '2021-01-01 11:00:00',
            '2021-01-02 10:00:00',
            false,
            null,
            '2021-01-03 10:00:00',
            false,
        ];
        yield 'timed range matches the exact start time' => [
            '2021-01-01 00:00:00',
            '2021-01-01 10:00:00',
            true,
            null,
            '2021-01-01 00:00:00',
            true,
        ];
        yield 'timed range rejects the second before the start' => [
            '2021-01-01 00:00:00',
            '2021-01-01 10:00:00',
            true,
            null,
            '2020-12-31 23:59:59',
            false,
        ];
        yield 'timed range matches the second before the end' => [
            '2021-01-01 00:00:00',
            '2021-01-01 10:00:00',
            true,
            null,
            '2021-01-01 09:59:59',
            true,
        ];
        yield 'timed range excludes the exact end time' => [
            '2021-01-01 00:00:00',
            '2021-01-01 10:00:00',
            true,
            null,
            '2021-01-01 10:00:00',
            false,
        ];
        yield 'open ended from date without time matches the start day' => [
            '2021-01-01 00:00:00',
            null,
            false,
            null,
            '2021-01-01 00:00:00',
            true,
        ];
        yield 'open ended from date without time rejects the previous day' => [
            '2021-01-01 00:00:00',
            null,
            false,
            null,
            '2020-12-31 23:59:59',
            false,
        ];
        yield 'open ended from date with time matches the exact start' => [
            '2021-01-01 00:00:00',
            null,
            true,
            null,
            '2021-01-01 00:00:00',
            true,
        ];
        yield 'open ended from date with time rejects the previous second' => [
            '2021-01-01 00:00:00',
            null,
            true,
            null,
            '2020-12-31 23:59:59',
            false,
        ];
        yield 'open ended to date without time includes the full end day' => [
            null,
            '2021-01-01 00:00:00',
            false,
            null,
            '2021-01-01 23:59:59',
            true,
        ];
        yield 'open ended to date without time rejects the next day' => [
            null,
            '2021-01-01 00:00:00',
            false,
            null,
            '2021-01-02 00:00:00',
            false,
        ];
        yield 'UTC timed range rejects a value after the end in negative offset' => [
            '2021-01-01 10:00:00',
            '2021-01-01 20:00:00',
            true,
            'UTC',
            '2021-01-01 20:00:00 -01:00',
            false,
        ];
        yield 'UTC timed range rejects a value after timezone normalization' => [
            '2021-01-01 10:00:00',
            '2021-01-01 20:00:00',
            true,
            'UTC',
            '2021-01-01 20:00:00 +01:00',
            false,
        ];
        yield 'UTC day range rejects a value normalized beyond the end day' => [
            '2021-01-01 00:00:00',
            '2021-01-01 00:00:00',
            false,
            'UTC',
            '2021-01-02 02:00:00 +04:00',
            false,
        ];
        yield 'GMT minus two day range rejects the previous UTC day boundary' => [
            '2021-01-02 00:00:00',
            '2021-01-02 00:00:00',
            false,
            'Etc/GMT-2',
            '2021-01-01 22:00:00',
            false,
        ];
        yield 'GMT minus two day range rejects the second before the UTC boundary' => [
            '2021-01-02 00:00:00',
            '2021-01-02 00:00:00',
            false,
            'Etc/GMT-2',
            '2021-01-01 21:59:59',
            false,
        ];
        yield 'GMT minus two timed range rejects the normalized end boundary' => [
            '2021-01-01 10:00:00',
            '2021-01-01 20:00:00',
            true,
            'Etc/GMT-2',
            '2021-01-01 08:00:00',
            false,
        ];
        yield 'GMT minus two timed range rejects the second after the normalized end' => [
            '2021-01-01 10:00:00',
            '2021-01-01 20:00:00',
            true,
            'Etc/GMT-2',
            '2021-01-01 07:59:59',
            false,
        ];
        yield 'empty date range always matches' => [
            null,
            null,
            true,
            null,
            '2021-01-01 07:59:59',
            true,
        ];
        yield 'timed ISO range rejects a value before the start' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            true,
            null,
            '2026-03-01T23:50:00',
            false,
        ];
        yield 'date only ISO range rejects a value before the start day' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            false,
            null,
            '2026-03-01T23:50:00',
            false,
        ];
        yield 'date only ISO range matches a value after the start' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            false,
            null,
            '2026-03-02T00:00:01',
            true,
        ];
        yield 'date only ISO range includes the end day timestamp' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            false,
            null,
            '2026-03-12T23:59:59',
            true,
        ];
        yield 'timed ISO range matches a value after the start' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            true,
            null,
            '2026-03-02T00:00:01',
            true,
        ];
        yield 'timed ISO range excludes the exact end timestamp' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            true,
            null,
            '2026-03-12T23:59:59',
            false,
        ];
        yield 'timed ISO range matches the second before the end timestamp' => [
            '2026-03-02T00:00:00',
            '2026-03-12T23:59:59',
            true,
            null,
            '2026-03-12T23:59:58',
            true,
        ];
    }

    public function testSerializationAndValidation(): void
    {
        $rule = new DateRangeRule(
            new \DateTime('2024-01-15 10:30:45'),
            new \DateTime('2024-01-31 23:59:59'),
            true,
            new \DateTimeZone('UTC')
        );

        $serialized = json_encode($rule);

        static::assertIsString($serialized);

        $data = json_decode($serialized, true);

        static::assertSame('2024-01-15T10:30:45', $data['fromDate']);
        static::assertSame('2024-01-31T23:59:59', $data['toDate']);

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $constraints = $rule->getConstraints();

        static::assertCount(0, $validator->validate($data['fromDate'], $constraints['fromDate']));
        static::assertCount(0, $validator->validate($data['toDate'], $constraints['toDate']));
    }

    public function testGetConstraints(): void
    {
        $constraints = (new DateRangeRule())->getConstraints();

        static::assertEquals([
            'fromDate' => [new NotBlank(), new DateTimeConstraint(format: 'Y-m-d\TH:i:s')],
            'toDate' => [new NotBlank(), new DateTimeConstraint(format: 'Y-m-d\TH:i:s')],
            'useTime' => [new NotNull(), new Type('bool')],
            'timezone' => [new Timezone()],
        ], $constraints);
    }

    #[DataProvider('invalidConstraintValuesProvider')]
    public function testConstraintsRejectInvalidValues(string $property, mixed $value, string $expectedCode): void
    {
        $constraints = (new DateRangeRule())->getConstraints();
        $validator = Validation::createValidator();

        $violations = $validator->validate($value, $constraints[$property]);

        static::assertCount(1, $violations);
        $violation = $violations->get(0);
        static::assertSame($expectedCode, $violation->getCode());
    }

    /**
     * @return \Generator<string, array{string, mixed, string}>
     */
    public static function invalidConstraintValuesProvider(): \Generator
    {
        yield 'missing fromDate' => ['fromDate', null, NotBlank::IS_BLANK_ERROR];
        yield 'missing toDate' => ['toDate', null, NotBlank::IS_BLANK_ERROR];
        yield 'invalid fromDate format' => ['fromDate', 'Invalid', DateTimeConstraint::INVALID_FORMAT_ERROR];
        yield 'invalid boolean fromDate format' => ['fromDate', true, DateTimeConstraint::INVALID_FORMAT_ERROR];
        yield 'invalid toDate format' => ['toDate', 'Invalid', DateTimeConstraint::INVALID_FORMAT_ERROR];
        yield 'invalid boolean toDate format' => ['toDate', true, DateTimeConstraint::INVALID_FORMAT_ERROR];
        yield 'missing useTime' => ['useTime', null, NotNull::IS_NULL_ERROR];
        yield 'invalid useTime type' => ['useTime', 'true', Type::INVALID_TYPE_ERROR];
        yield 'invalid timezone' => ['timezone', 'Invalid/Timezone', Timezone::TIMEZONE_IDENTIFIER_ERROR];
    }

    public function testAssignWithStringDatesConvertsToDateTime(): void
    {
        $rule = new DateRangeRule();

        $rule->assign([
            'fromDate' => '2024-01-15T10:30:45',
            'toDate' => '2024-01-31T23:59:59',
            'useTime' => true,
            'timezone' => 'UTC',
        ]);

        $scopeMock = $this->createMock(RuleScope::class);
        $scopeMock->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2024-01-20 12:00:00'));

        $result = $rule->match($scopeMock);

        static::assertTrue($result);
    }

    public function testWakeupSetsTimezoneToNullWhenMissingInLegacySerializedData(): void
    {
        $legacySerialized = 'O:42:"Shopware\\Core\\Framework\\Rule\\DateRangeRule":5:{'
            . "s:13:\"\0*\0extensions\";a:0:{}"
            . "s:8:\"\0*\0_name\";s:9:\"dateRange\";"
            . "s:11:\"\0*\0fromDate\";O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2026-01-01 00:00:00.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:3:\"UTC\";}"
            . "s:9:\"\0*\0toDate\";O:8:\"DateTime\":3:{s:4:\"date\";s:26:\"2026-01-16 23:59:59.000000\";s:13:\"timezone_type\";i:3;s:8:\"timezone\";s:3:\"UTC\";}"
            . "s:10:\"\0*\0useTime\";b:0;";

        $unserializedRule = Serialization::assertUnserializedInstanceOf(DateRangeRule::class, $legacySerialized . '}');

        $timezone = (new \ReflectionProperty(DateRangeRule::class, 'timezone'))->getValue($unserializedRule);
        static::assertNull($timezone);

        $scopeMock = $this->createMock(RuleScope::class);
        $scopeMock->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2026-01-10 12:00:00'));
        static::assertTrue($unserializedRule->match($scopeMock));
    }

    /**
     * @param array<string, string|bool|\DateTime|null> $options
     */
    #[DataProvider('provideInvalidDateAndTimezoneFormats')]
    public function testAssignPreservesInvalidFormatsForValidators(array $options): void
    {
        $rule = new DateRangeRule();

        $rule = $rule->assign($options);

        $fromDate = (new \ReflectionProperty(DateRangeRule::class, 'fromDate'))->getValue($rule);
        $toDate = (new \ReflectionProperty(DateRangeRule::class, 'toDate'))->getValue($rule);
        $useTime = (new \ReflectionProperty(DateRangeRule::class, 'useTime'))->getValue($rule);
        $timezone = (new \ReflectionProperty(DateRangeRule::class, 'timezone'))->getValue($rule);

        $result = [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'useTime' => $useTime,
            'timezone' => $timezone,
        ];

        static::assertSame($options, $result);
    }

    public static function provideInvalidDateAndTimezoneFormats(): \Generator
    {
        yield 'invalid fromDate format' => [
            'options' => [
                'fromDate' => 'not-a-valid-date',
                'toDate' => '2024-12-31T23:59:59',
                'useTime' => true,
                'timezone' => null,
            ],
        ];

        yield 'invalid toDate format' => [
            'options' => [
                'fromDate' => new \DateTime('2024-01-01T00:00:00'),
                'toDate' => 'invalid-to-date',
                'useTime' => false,
                'timezone' => null,
            ],
        ];

        yield 'invalid timezone' => [
            'options' => [
                'fromDate' => new \DateTime('2024-01-01T00:00:00'),
                'toDate' => new \DateTime('2024-12-31T23:59:59'),
                'useTime' => true,
                'timezone' => 'not-a-valid-timezone',
            ],
        ];

        yield 'all invalid values' => [
            'options' => [
                'fromDate' => 'invalid-from',
                'toDate' => 'invalid-to',
                'useTime' => false,
                'timezone' => null,
            ],
        ];
    }

    /**
     * @param array<string, string> $properties
     */
    #[DataProvider('provideInvalidStringValuesForMatch')]
    public function testMatchThrowsExceptionWhenDatePropertiesAreStrings(array $properties): void
    {
        $rule = new DateRangeRule(...$properties);

        $this->expectExceptionObject(
            RuleException::invalidDateRangeUsage('fromDate, toDate and timezone cannot be a string at this point')
        );

        $rule->match($this->createMock(RuleScope::class));
    }

    public static function provideInvalidStringValuesForMatch(): \Generator
    {
        yield 'fromDate is string' => [
            'properties' => [
                'fromDate' => '2024-01-15T10:30:45',
            ],
        ];

        yield 'toDate is string' => [
            'properties' => [
                'toDate' => '2024-12-31T23:59:59',
            ],
        ];

        yield 'timezone is string' => [
            'properties' => [
                'timezone' => 'Europe/Berlin',
            ],
        ];

        yield 'all properties are strings' => [
            'properties' => [
                'fromDate' => '2024-01-15T10:30:45',
                'toDate' => '2024-12-31T23:59:59',
                'timezone' => 'UTC',
            ],
        ];

        yield 'fromDate and toDate are strings' => [
            'properties' => [
                'fromDate' => '2024-01-15T10:30:45',
                'toDate' => '2024-12-31T23:59:59',
            ],
        ];
    }
}
