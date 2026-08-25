<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * Canonicalises an authored ElementStyle into the form a write stores: a box-spacing value becomes
 * explicit four-part CSS, a breakpoint-aware scalar is wrapped into the full breakpoint map, a partial
 * map of an option that declares a default is filled out from that default, and every value that is
 * indistinguishable from unset is dropped. A style whose every value drops normalises to an empty
 * ElementStyle, which the element encoders omit.
 *
 * Idempotent by contract: normalising an already normalised style returns it unchanged.
 *
 * Two deliberate pass-throughs, both reproduced from the algorithm this ports: an option with no
 * registry entry rides through untouched, and a value classified as unset is dropped rather than
 * rejected. Everything else about the input is left for the write constraints to judge.
 *
 * The registry is read through the strict all(), the same view the write-boundary constraint
 * descriptor uses, so the two cannot disagree about which options exist.
 *
 * @internal
 *
 * @phpstan-type StyleScalar string|int|float|bool
 * @phpstan-type StyleValue StyleScalar|array<string, StyleScalar>
 */
#[Package('framework')]
final class ElementStyleNormalizer
{
    public function __construct(
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
        private readonly BoxSpacingNormalizer $boxSpacingNormalizer,
    ) {
    }

    public function normalize(ElementStyle $style): ElementStyle
    {
        $options = $this->registry->all();

        $normalized = [];

        foreach ($style->toArray() as $name => $value) {
            $normalizedValue = $this->normalizeValue($value, $options[$name] ?? null);

            if ($normalizedValue === null) {
                continue;
            }

            $normalized[$name] = $normalizedValue;
        }

        return new ElementStyle($normalized);
    }

    /**
     * @param StyleValue $value
     *
     * @return StyleValue|null null drops the option from the written style
     */
    private function normalizeValue(mixed $value, ?StyleOptionSpecification $option): string|int|float|bool|array|null
    {
        $resolved = $this->isBoxSpacingOption($option) ? $this->normalizeBoxSpacing($value) : $value;

        if ($this->isUnsetValue($resolved, $option)) {
            return null;
        }

        if ($option === null || !$option->breakpointAware()) {
            return $resolved;
        }

        if (!\is_array($resolved)) {
            return array_fill_keys(Breakpoint::values(), $resolved);
        }

        if ($option->valueType()->default() === null) {
            return $resolved;
        }

        return $this->expandBreakpointMap($resolved, $option);
    }

    /**
     * @param StyleValue $value
     *
     * @return string|array<string, string>
     */
    private function normalizeBoxSpacing(mixed $value): string|array
    {
        if (!\is_array($value)) {
            return $this->boxSpacingNormalizer->normalizeCssValue($value);
        }

        $normalized = [];

        foreach ($value as $breakpoint => $entryValue) {
            $normalized[$breakpoint] = $this->boxSpacingNormalizer->normalizeCssValue($entryValue);
        }

        return $normalized;
    }

    private function isBoxSpacingOption(?StyleOptionSpecification $option): bool
    {
        return $option?->kind() === StyleOptionSpecification::KIND_BOX_SPACING;
    }

    /**
     * An unregistered option is never unset — it rides through with whatever it holds.
     *
     * @param StyleValue $value
     */
    private function isUnsetValue(mixed $value, ?StyleOptionSpecification $option): bool
    {
        if ($value === '') {
            return true;
        }

        if ($option === null) {
            return false;
        }

        if (!\is_array($value)) {
            return $this->isUnsetScalar($value, $option);
        }

        $entries = $option->valueType()->default() === null
            ? $value
            : $this->expandBreakpointMap($value, $option);

        $defined = [];

        foreach ($entries as $entryValue) {
            if ($entryValue === '') {
                continue;
            }

            $defined[] = $entryValue;
        }

        if ($defined === []) {
            return true;
        }

        foreach ($defined as $entryValue) {
            if (!$this->isUnsetScalar($entryValue, $option)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param StyleScalar $value
     */
    private function isUnsetScalar(string|int|float|bool $value, StyleOptionSpecification $option): bool
    {
        if ($value === '') {
            return true;
        }

        $valueType = $option->valueType();
        $default = $valueType->default();

        // The reference returns here unconditionally once a default is declared, so the range-minimum
        // elision below applies only to an option that declares none.
        if ($default !== null) {
            return $value === $default;
        }

        $type = $valueType->type();

        if ($type !== StyleOptionValueType::TYPE_INTEGER && $type !== StyleOptionValueType::TYPE_NUMBER) {
            return false;
        }

        $range = $valueType->range();

        return $range !== null && \array_key_exists('min', $range) && $value === $range['min'];
    }

    /**
     * Rebuilds the map from the canonical breakpoints alone, so a key that is not one of them is dropped.
     * A breakpoint with no entry, or an empty one, takes the option default.
     *
     * @param array<string, StyleScalar> $value
     *
     * @return array<string, StyleScalar>
     */
    private function expandBreakpointMap(array $value, StyleOptionSpecification $option): array
    {
        $default = $option->valueType()->default();

        if ($default === null || !$this->isViewportSpecific($value)) {
            return $value;
        }

        $expanded = [];

        foreach (Breakpoint::values() as $breakpoint) {
            $entryValue = $value[$breakpoint] ?? null;

            $expanded[$breakpoint] = $entryValue !== null && $entryValue !== '' ? $entryValue : $default;
        }

        return $expanded;
    }

    /**
     * False both when no breakpoint carries an entry and when every breakpoint carries the same entry —
     * in either case there is nothing viewport-specific to expand.
     *
     * @param array<string, StyleScalar> $value
     */
    private function isViewportSpecific(array $value): bool
    {
        $breakpoints = Breakpoint::values();

        $defined = [];

        foreach ($breakpoints as $breakpoint) {
            $entryValue = $value[$breakpoint] ?? null;

            if ($entryValue === null) {
                continue;
            }

            $defined[] = $entryValue;
        }

        if ($defined === []) {
            return false;
        }

        if (\count($defined) < \count($breakpoints)) {
            return true;
        }

        $first = $defined[0];

        foreach ($defined as $entryValue) {
            if ($entryValue !== $first) {
                return true;
            }
        }

        return false;
    }
}
