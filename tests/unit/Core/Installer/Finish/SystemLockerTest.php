<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SystemLocker::class)]
class SystemLockerTest extends TestCase
{
    use EnvTestBehaviour;

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/install.lock');
    }

    public function testLock(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testLockFileContainsTimestamp(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        $content = file_get_contents(__DIR__ . '/install.lock');
        static::assertNotEmpty($content);
        // The file should contain a timestamp in YmdHi format
        static::assertMatchesRegularExpression('/^\d{12}$/', $content);
    }

    #[DataProvider('truthyEnvironmentVariableProvider')]
    public function testLockSkippedWhenEnvironmentVariableIsTruthy(string $envValue): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => $envValue]);

        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileDoesNotExist(__DIR__ . '/install.lock', \sprintf('Lock file should not exist when env var is "%s"', $envValue));
    }

    public static function truthyEnvironmentVariableProvider(): \Generator
    {
        yield 'string 1' => ['envValue' => '1'];
        yield 'string true' => ['envValue' => 'true'];
        yield 'string TRUE' => ['envValue' => 'TRUE'];
        yield 'string yes' => ['envValue' => 'yes'];
        yield 'string YES' => ['envValue' => 'YES'];
        yield 'string on' => ['envValue' => 'on'];
        yield 'string enabled' => ['envValue' => 'enabled'];

        yield 'string false (evaluates to true!)' => ['envValue' => 'false'];
        yield 'string FALSE' => ['envValue' => 'FALSE'];
        yield 'string no (evaluates to true!)' => ['envValue' => 'no'];
        yield 'string off (evaluates to true!)' => ['envValue' => 'off'];
        yield 'any non-empty string' => ['envValue' => 'anything'];
        yield 'string 2' => ['envValue' => '2'];
        yield 'spaces only' => ['envValue' => '   '];
    }

    #[DataProvider('falsyEnvironmentVariableProvider')]
    public function testLockCreatedWhenEnvironmentVariableIsFalsy(?string $envValue): void
    {
        if ($envValue !== null) {
            $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => $envValue]);
        }

        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileExists(__DIR__ . '/install.lock', \sprintf('Lock file should exist when env var is "%s"', $envValue ?? 'null'));
    }

    public static function falsyEnvironmentVariableProvider(): \Generator
    {
        yield 'string 0' => ['envValue' => '0'];
        yield 'empty string' => ['envValue' => ''];
        yield 'null (not set)' => ['envValue' => null];
    }
}
