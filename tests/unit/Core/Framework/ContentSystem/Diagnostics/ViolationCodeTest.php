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
        yield 'unregistered_component' => [ViolationCode::UnregisteredComponent, ViolationScope::Intrinsic];
        yield 'duplicate_element_id' => [ViolationCode::DuplicateElementId, ViolationScope::Intrinsic];
        yield 'invalid_config' => [ViolationCode::InvalidConfig, ViolationScope::Intrinsic];
        yield 'unresolved_required' => [ViolationCode::UnresolvedRequired, ViolationScope::Binding];
        yield 'ambiguous_required' => [ViolationCode::AmbiguousRequired, ViolationScope::Binding];
        yield 'broken_required_chain' => [ViolationCode::BrokenRequiredChain, ViolationScope::Binding];
        yield 'unresolved_optional' => [ViolationCode::UnresolvedOptional, ViolationScope::Binding];
        yield 'orphaned_provider' => [ViolationCode::OrphanedProvider, ViolationScope::Intrinsic];
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationSeverity}>
     */
    public static function derivesSeverityProvider(): iterable
    {
        yield 'unregistered_component' => [ViolationCode::UnregisteredComponent, ViolationSeverity::Error];
        yield 'duplicate_element_id' => [ViolationCode::DuplicateElementId, ViolationSeverity::Error];
        yield 'invalid_config' => [ViolationCode::InvalidConfig, ViolationSeverity::Error];
        yield 'unresolved_required' => [ViolationCode::UnresolvedRequired, ViolationSeverity::Error];
        yield 'ambiguous_required' => [ViolationCode::AmbiguousRequired, ViolationSeverity::Error];
        yield 'broken_required_chain' => [ViolationCode::BrokenRequiredChain, ViolationSeverity::Error];
        yield 'unresolved_optional' => [ViolationCode::UnresolvedOptional, ViolationSeverity::Warning];
        yield 'orphaned_provider' => [ViolationCode::OrphanedProvider, ViolationSeverity::Warning];
    }
}
