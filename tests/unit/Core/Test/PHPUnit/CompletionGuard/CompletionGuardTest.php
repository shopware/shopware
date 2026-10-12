<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\CompletionGuard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompletionGuard::class)]
class CompletionGuardTest extends TestCase
{
    /**
     * @param array{type: int, message: string, file: string, line: int}|null $lastError
     */
    #[DataProvider('shutdownStateProvider')]
    #[TestDox('shouldForceFailure is $expected for $_dataName')]
    public function testShouldForceFailure(bool $started, bool $finished, ?array $lastError, bool $expected): void
    {
        static::assertSame($expected, CompletionGuard::shouldForceFailure($started, $finished, $lastError));
    }

    /**
     * @return \Generator<string, array{bool, bool, array{type: int, message: string, file: string, line: int}|null, bool}>
     */
    public static function shutdownStateProvider(): \Generator
    {
        yield 'execution never started (--list-tests, bootstrap failure)' => [false, false, null, false];

        yield 'execution finished normally' => [true, true, null, false];

        yield 'execution started but never finished' => [true, false, null, true];

        yield 'execution started but never finished, earlier warning' => [true, false, self::error(\E_WARNING), true];

        yield 'fatal error keeps its own exit code' => [true, false, self::error(\E_ERROR), false];

        yield 'compile error keeps its own exit code' => [true, false, self::error(\E_COMPILE_ERROR), false];

        yield 'parse error keeps its own exit code' => [true, false, self::error(\E_PARSE), false];

        yield 'core error keeps its own exit code' => [true, false, self::error(\E_CORE_ERROR), false];
    }

    /**
     * @return array{type: int, message: string, file: string, line: int}
     */
    private static function error(int $type): array
    {
        return ['type' => $type, 'message' => 'whoops', 'file' => 'Some/File.php', 'line' => 42];
    }
}
