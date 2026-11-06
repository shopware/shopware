<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestEnvironment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestEnvironment::class)]
class TestEnvironmentTest extends TestCase
{
    public function testSetWritesAllThreeSources(): void
    {
        TestEnvironment::set(['TEST_ENVIRONMENT_MARKER' => 'set', 'TEST_ENVIRONMENT_FLAG' => true]);

        static::assertSame('set', $_SERVER['TEST_ENVIRONMENT_MARKER']);
        static::assertSame('set', $_ENV['TEST_ENVIRONMENT_MARKER']);
        static::assertSame('set', getenv('TEST_ENVIRONMENT_MARKER'));
        static::assertTrue($_SERVER['TEST_ENVIRONMENT_FLAG']);
        static::assertSame('1', getenv('TEST_ENVIRONMENT_FLAG'));
    }

    public function testNullRemovesFromAllThreeSources(): void
    {
        TestEnvironment::set(['TEST_ENVIRONMENT_MARKER' => 'set']);
        TestEnvironment::set(['TEST_ENVIRONMENT_MARKER' => null]);

        static::assertArrayNotHasKey('TEST_ENVIRONMENT_MARKER', $_SERVER);
        static::assertArrayNotHasKey('TEST_ENVIRONMENT_MARKER', $_ENV);
        static::assertFalse(getenv('TEST_ENVIRONMENT_MARKER'));
    }

    public function testResetPutsBackWhatTheTestOverwrote(): void
    {
        $_SERVER['TEST_ENVIRONMENT_EXISTING'] = 'before';
        TestEnvironment::set(['TEST_ENVIRONMENT_EXISTING' => 'during', 'TEST_ENVIRONMENT_NEW' => 'during']);
        TestEnvironment::set(['TEST_ENVIRONMENT_EXISTING' => 'later']);

        TestEnvironment::reset();

        static::assertSame('before', $_SERVER['TEST_ENVIRONMENT_EXISTING']);
        static::assertArrayNotHasKey('TEST_ENVIRONMENT_NEW', $_SERVER);
        static::assertFalse(getenv('TEST_ENVIRONMENT_NEW'));
    }

    public function testRestoreRevertsEnvAndRealEnvironmentToTheSnapshot(): void
    {
        $snapshot = TestEnvironment::snapshot();

        TestEnvironment::set(['TEST_ENVIRONMENT_ADDED' => 'added']);
        putenv('TEST_ENVIRONMENT_RAW=raw');

        TestEnvironment::restore($snapshot);

        static::assertArrayNotHasKey('TEST_ENVIRONMENT_ADDED', $_ENV);
        static::assertFalse(getenv('TEST_ENVIRONMENT_ADDED'));
        static::assertFalse(getenv('TEST_ENVIRONMENT_RAW'));
    }

    public function testRestoreForgetsTheOriginalsSoResetIsANoOpAfterwards(): void
    {
        $snapshot = TestEnvironment::snapshot();
        TestEnvironment::set(['TEST_ENVIRONMENT_ADDED' => 'added']);
        TestEnvironment::restore($snapshot);

        $_SERVER['TEST_ENVIRONMENT_ADDED'] = 'after restore';
        TestEnvironment::reset();

        static::assertSame('after restore', $_SERVER['TEST_ENVIRONMENT_ADDED']);
        unset($_SERVER['TEST_ENVIRONMENT_ADDED']);
    }
}
