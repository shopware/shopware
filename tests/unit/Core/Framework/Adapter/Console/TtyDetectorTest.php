<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Console\TtyDetector;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(TtyDetector::class)]
class TtyDetectorTest extends TestCase
{
    use EnvTestBehaviour;

    public function testFallsBackToStreamIsattyWhenNoMsystem(): void
    {
        $this->setEnvVars(['MSYSTEM' => '']);

        $detector = new TtyDetector();

        // In PHPUnit, STDIN is not a TTY, so this should return false
        static::assertFalse($detector->isStdinTty());
    }

    public function testReturnsBool(): void
    {
        $detector = new TtyDetector();

        static::assertIsBool($detector->isStdinTty());
    }

    #[DataProvider('mingwEnvironmentProvider')]
    public function testReturnsTrueForMingwMsysEnvironment(string $msystem): void
    {
        $this->setEnvVars(['MSYSTEM' => $msystem]);

        $detector = new TtyDetector();

        static::assertTrue($detector->isStdinTty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mingwEnvironmentProvider(): iterable
    {
        yield 'MINGW32' => ['MINGW32'];
        yield 'MINGW64' => ['MINGW64'];
        yield 'MSYS' => ['MSYS'];
        yield 'mingw32 lowercase' => ['mingw32'];
        yield 'mingw64 lowercase' => ['mingw64'];
        yield 'msys lowercase' => ['msys'];
    }
}
