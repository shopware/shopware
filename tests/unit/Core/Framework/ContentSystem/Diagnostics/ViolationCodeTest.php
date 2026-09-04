<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationScope;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationSeverity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('keeps the scope and severity providers exhaustive over every violation code')]
    public function testProvidersCoverEveryViolationCode(): void
    {
        $scopeCodes = array_values(array_map(
            static fn (array $row): ViolationCode => $row[0],
            iterator_to_array(self::derivesScopeProvider()),
        ));
        $severityCodes = array_values(array_map(
            static fn (array $row): ViolationCode => $row[0],
            iterator_to_array(self::derivesSeverityProvider()),
        ));

        static::assertEqualsCanonicalizing(ViolationCode::cases(), $scopeCodes);
        static::assertEqualsCanonicalizing(ViolationCode::cases(), $severityCodes);
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationScope}>
     */
    public static function derivesScopeProvider(): iterable
    {
        yield 'unregistered_component' => [ViolationCode::UnregisteredComponent, ViolationScope::Intrinsic];
        yield 'duplicate_element_id' => [ViolationCode::DuplicateElementId, ViolationScope::Intrinsic];
        yield 'invalid_config' => [ViolationCode::InvalidConfig, ViolationScope::Intrinsic];
        yield 'mismatched_reference_type' => [ViolationCode::MismatchedReferenceType, ViolationScope::Intrinsic];
        yield 'mismatched_property_type' => [ViolationCode::MismatchedPropertyType, ViolationScope::Intrinsic];
        yield 'unknown_style_option' => [ViolationCode::UnknownStyleOption, ViolationScope::Intrinsic];
        yield 'orphaned_provider' => [ViolationCode::OrphanedProvider, ViolationScope::Intrinsic];
        yield 'unresolved_required' => [ViolationCode::UnresolvedRequired, ViolationScope::Binding];
        yield 'ambiguous_required' => [ViolationCode::AmbiguousRequired, ViolationScope::Binding];
        yield 'broken_required_chain' => [ViolationCode::BrokenRequiredChain, ViolationScope::Binding];
        yield 'unresolved_optional' => [ViolationCode::UnresolvedOptional, ViolationScope::Binding];
        yield 'unfilled_required_input' => [ViolationCode::UnfilledRequiredInput, ViolationScope::Binding];
    }

    /**
     * @return iterable<string, array{ViolationCode, ViolationSeverity}>
     */
    public static function derivesSeverityProvider(): iterable
    {
        yield 'unregistered_component' => [ViolationCode::UnregisteredComponent, ViolationSeverity::Error];
        yield 'duplicate_element_id' => [ViolationCode::DuplicateElementId, ViolationSeverity::Error];
        yield 'invalid_config' => [ViolationCode::InvalidConfig, ViolationSeverity::Error];
        yield 'mismatched_reference_type' => [ViolationCode::MismatchedReferenceType, ViolationSeverity::Error];
        yield 'mismatched_property_type' => [ViolationCode::MismatchedPropertyType, ViolationSeverity::Error];
        yield 'unknown_style_option' => [ViolationCode::UnknownStyleOption, ViolationSeverity::Error];
        yield 'unresolved_required' => [ViolationCode::UnresolvedRequired, ViolationSeverity::Error];
        yield 'ambiguous_required' => [ViolationCode::AmbiguousRequired, ViolationSeverity::Error];
        yield 'broken_required_chain' => [ViolationCode::BrokenRequiredChain, ViolationSeverity::Error];
        yield 'unfilled_required_input' => [ViolationCode::UnfilledRequiredInput, ViolationSeverity::Error];
        yield 'unresolved_optional' => [ViolationCode::UnresolvedOptional, ViolationSeverity::Warning];
        yield 'orphaned_provider' => [ViolationCode::OrphanedProvider, ViolationSeverity::Warning];
    }
}
