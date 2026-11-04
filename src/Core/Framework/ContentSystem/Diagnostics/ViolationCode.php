<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\Log\Package;

/**
 * The single source of truth for a violation's scope and severity, so an illegal code/scope/severity
 * triple is unrepresentable: a {@see Violation} carries a code and derives the rest.
 *
 * @internal
 */
#[Package('framework')]
enum ViolationCode: string
{
    case UnregisteredComponent = 'unregistered_component';
    case DuplicateElementId = 'duplicate_element_id';
    case InvalidConfig = 'invalid_config';
    case MismatchedReferenceType = 'mismatched_reference_type';
    case MismatchedPropertyType = 'mismatched_property_type';
    case UnresolvedRequired = 'unresolved_required';
    case AmbiguousRequired = 'ambiguous_required';
    case BrokenRequiredChain = 'broken_required_chain';
    case UnresolvedOptional = 'unresolved_optional';
    case OrphanedProvider = 'orphaned_provider';
    case UnfilledRequiredInput = 'unfilled_required_input';
    case UnknownStyleOption = 'unknown_style_option';
    case InvalidMapping = 'invalid_mapping';

    public function scope(): ViolationScope
    {
        return match ($this) {
            self::UnregisteredComponent,
            self::DuplicateElementId,
            self::InvalidConfig,
            self::MismatchedReferenceType,
            self::MismatchedPropertyType,
            self::UnknownStyleOption,
            self::OrphanedProvider => ViolationScope::Intrinsic,
            self::UnresolvedRequired,
            self::AmbiguousRequired,
            self::BrokenRequiredChain,
            self::UnresolvedOptional,
            self::UnfilledRequiredInput,
            // Binding rather than intrinsic, and not a judgement call: a mapping is admissible only against
            // a particular root source's catalogue, so the same tree is legal under one bound source and not
            // under another. That dependence on the binding is exactly what the scope names.
            self::InvalidMapping => ViolationScope::Binding,
        };
    }

    public function severity(): ViolationSeverity
    {
        return match ($this) {
            self::UnregisteredComponent,
            self::DuplicateElementId,
            self::InvalidConfig,
            self::MismatchedReferenceType,
            self::MismatchedPropertyType,
            self::UnknownStyleOption,
            self::UnresolvedRequired,
            self::AmbiguousRequired,
            self::BrokenRequiredChain,
            self::UnfilledRequiredInput,
            self::InvalidMapping => ViolationSeverity::Error,
            self::UnresolvedOptional,
            self::OrphanedProvider => ViolationSeverity::Warning,
        };
    }
}
