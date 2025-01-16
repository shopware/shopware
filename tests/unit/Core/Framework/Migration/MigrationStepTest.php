<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(MigrationStep::class)]
class MigrationStepTest extends TestCase
{
    #[DataProvider('throwingMigrationTimestamps')]
    public function testImplausibleMigrationTimestampThrows(int $timestamp): void
    {
        $step = new TimestampMigrationStep($timestamp);

        $this->expectExceptionObject(MigrationException::implausibleCreationTimestamp($timestamp, $step));

        $step->getPlausibleCreationTimestamp();
    }

    #[DataProvider('throwingMigrationTimestamps')]
    #[DisabledFeatures(['v6.7.0.0'])]
    /**
     * @deprecated tag:v6.7.0 - This test is only relevant to ensure backwards compatibility in 6.6
     */
    public function testImplausibleMigrationTimestampSucceedsIn66(int $timestamp): void
    {
        $step = new TimestampMigrationStep($timestamp);
        static::assertSame($timestamp, $step->getPlausibleCreationTimestamp());
    }

    public static function throwingMigrationTimestamps(): \Generator
    {
        yield 'negative' => [-1];
        yield 'zero' => [0];
        yield '32 bit max int' => [2147483647];
        yield '64 bit max int' => [9223372036854775807];
    }

    #[DataProvider('validMigrationTimestamps')]
    public function testValidTimestamps(int $timestamp): void
    {
        $step = new TimestampMigrationStep($timestamp);
        static::assertSame($timestamp, $step->getPlausibleCreationTimestamp());
    }

    public static function validMigrationTimestamps(): \Generator
    {
        yield 'one' => [1];
        yield '32 bit max int - 1' => [2147483646];
        yield 'current timestamp' => [time()];
    }
}

/**
 * @internal
 */
class TimestampMigrationStep extends MigrationStep
{
    public function __construct(private int $timestamp)
    {
    }

    public function getCreationTimestamp(): int
    {
        return $this->timestamp;
    }

    public function update(Connection $connection): void
    {
    }
}
