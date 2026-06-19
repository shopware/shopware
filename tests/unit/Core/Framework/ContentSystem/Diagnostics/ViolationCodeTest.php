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
    #[DataProvider('codeProvider')]
    #[TestDox('$_dataName derives its scope and severity from the code')]
    public function testScopeAndSeverityDerivation(ViolationCode $code, ViolationScope $scope, ViolationSeverity $severity): void
    {
        static::assertSame($scope, $code->scope());
        static::assertSame($severity, $code->severity());
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationScope, ViolationSeverity}>
     */
    public static function codeProvider(): iterable
    {
        yield 'unregistered_component' => [ViolationCode::UnregisteredComponent, ViolationScope::Intrinsic, ViolationSeverity::Error];
        yield 'duplicate_element_id' => [ViolationCode::DuplicateElementId, ViolationScope::Intrinsic, ViolationSeverity::Error];
        yield 'invalid_config' => [ViolationCode::InvalidConfig, ViolationScope::Intrinsic, ViolationSeverity::Error];
        yield 'unresolved_required' => [ViolationCode::UnresolvedRequired, ViolationScope::Binding, ViolationSeverity::Error];
        yield 'ambiguous_required' => [ViolationCode::AmbiguousRequired, ViolationScope::Binding, ViolationSeverity::Error];
        yield 'broken_required_chain' => [ViolationCode::BrokenRequiredChain, ViolationScope::Binding, ViolationSeverity::Error];
        yield 'unresolved_optional' => [ViolationCode::UnresolvedOptional, ViolationScope::Binding, ViolationSeverity::Warning];
        yield 'orphaned_provider' => [ViolationCode::OrphanedProvider, ViolationScope::Intrinsic, ViolationSeverity::Warning];
    }
}
