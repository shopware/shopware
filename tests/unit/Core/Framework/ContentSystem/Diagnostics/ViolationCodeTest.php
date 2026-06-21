<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationScope;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationSeverity;

/**
 * @internal
 */
#[CoversClass(ViolationCode::class)]
class ViolationCodeTest extends TestCase
{
    #[DataProvider('derivesScopeProvider')]
    #[TestDox('derives scope from $_dataName')]
    public function testDerivesScopeFromCode(ViolationCode $code, ViolationScope $expectedScope): void
    {
        static::assertSame($expectedScope, $code->scope());
    }

    #[DataProvider('derivesSeverityProvider')]
    #[TestDox('derives severity from $_dataName')]
    public function testDerivesSeverityFromCode(ViolationCode $code, ViolationSeverity $expectedSeverity): void
    {
        static::assertSame($expectedSeverity, $code->severity());
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationScope}>
     */
    public static function derivesScopeProvider(): iterable
    {
        // scope() is a two-arm match; one representative per arm pins both branches.
        yield 'an intrinsic-scope code' => [ViolationCode::UnregisteredComponent, ViolationScope::Intrinsic];
        yield 'a binding-scope code' => [ViolationCode::UnresolvedRequired, ViolationScope::Binding];
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationSeverity}>
     */
    public static function derivesSeverityProvider(): iterable
    {
        // severity() is a two-arm match; one representative per arm pins both branches.
        yield 'an error-severity code' => [ViolationCode::UnregisteredComponent, ViolationSeverity::Error];
        yield 'a warning-severity code' => [ViolationCode::UnresolvedOptional, ViolationSeverity::Warning];
    }
}
