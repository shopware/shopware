/**
 * @private
 * @sw-package discovery
 */
export type BoxSpacingSide = 'top' | 'right' | 'bottom' | 'left';

/**
 * @private
 * @sw-package discovery
 */
export type BoxSpacingSides = Record<BoxSpacingSide, string>;

const EMPTY_SIDES: BoxSpacingSides = {
    top: '',
    right: '',
    bottom: '',
    left: '',
};

const PLAIN_NUMBER_PATTERN = /^-?(\d+(\.\d+)?|\.\d+)$/;
const PLAIN_NUMBER_WITH_PX_PATTERN = /^(-?(\d+(\.\d+)?|\.\d+))px$/i;
const CSS_KEYWORD_PATTERN = /^(auto|inherit|initial|unset|revert)$/i;
const HAS_CSS_UNIT_PATTERN = /^-?(\d+(\.\d+)?|\.\d+)[a-z%]+$/i;

/**
 * Strips auto-added `px` for display in side inputs. User-provided units (% , rem, etc.) are kept.
 *
 * @private
 * @sw-package discovery
 */
export function formatBoxSpacingSideForInput(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    const pxMatch = trimmed.match(PLAIN_NUMBER_WITH_PX_PATTERN);

    if (pxMatch) {
        return pxMatch[1];
    }

    return trimmed;
}

/**
 * @private
 * @sw-package discovery
 */
export function normalizeBoxSpacingUnit(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '' || trimmed === '0') {
        return trimmed === '' ? '' : '0';
    }

    if (CSS_KEYWORD_PATTERN.test(trimmed)) {
        return trimmed.toLowerCase();
    }

    if (trimmed.includes('(') || HAS_CSS_UNIT_PATTERN.test(trimmed)) {
        return trimmed;
    }

    if (PLAIN_NUMBER_PATTERN.test(trimmed)) {
        return `${trimmed}px`;
    }

    return trimmed;
}

/**
 * @private
 * @sw-package discovery
 */
export function normalizeBoxSpacingSide(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '0';
    }

    return normalizeBoxSpacingUnit(trimmed);
}

function toInputSides(sides: BoxSpacingSides): BoxSpacingSides {
    return {
        top: formatBoxSpacingSideForInput(sides.top),
        right: formatBoxSpacingSideForInput(sides.right),
        bottom: formatBoxSpacingSideForInput(sides.bottom),
        left: formatBoxSpacingSideForInput(sides.left),
    };
}

/**
 * @private
 * @sw-package discovery
 */
export function parseBoxSpacing(value: string | null | undefined): BoxSpacingSides {
    if (value === null || value === undefined) {
        return { ...EMPTY_SIDES };
    }

    if (value === '') {
        return { ...EMPTY_SIDES };
    }

    const explicitParts = String(value).split(' ');

    if (explicitParts.length === 4) {
        return toInputSides({
            top: explicitParts[0],
            right: explicitParts[1],
            bottom: explicitParts[2],
            left: explicitParts[3],
        });
    }

    const normalized = String(value).trim();

    if (normalized === '') {
        return { ...EMPTY_SIDES };
    }

    const parts = normalized.split(/\s+/);

    if (parts.length === 1) {
        return toInputSides({
            top: parts[0],
            right: parts[0],
            bottom: parts[0],
            left: parts[0],
        });
    }

    if (parts.length === 2) {
        return toInputSides({
            top: parts[0],
            right: parts[1],
            bottom: parts[0],
            left: parts[1],
        });
    }

    if (parts.length === 3) {
        return toInputSides({
            top: parts[0],
            right: parts[1],
            bottom: parts[2],
            left: parts[1],
        });
    }

    return toInputSides({
        top: parts[0] ?? '',
        right: parts[1] ?? '',
        bottom: parts[2] ?? '',
        left: parts[3] ?? '',
    });
}

/**
 * @private
 * @sw-package discovery
 */
export type SerializeBoxSpacingOptions = {
    linked?: boolean;
    explicit?: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export function isBoxSpacingStyleOption(
    option:
        | {
              adminUI?: {
                  component?: string;
              } | null;
          }
        | undefined,
): boolean {
    return option?.adminUI?.component === 'box-spacing';
}

/**
 * @private
 * @sw-package discovery
 */
export function normalizeBoxSpacingCSSValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return serializeBoxSpacing(parseBoxSpacing(String(value)), { explicit: true });
    }

    if (typeof value !== 'string') {
        return '';
    }

    const stringValue = value.trim();

    if (stringValue === '') {
        return '';
    }

    return serializeBoxSpacing(parseBoxSpacing(stringValue), { explicit: true });
}

/**
 * @private
 * @sw-package discovery
 */
export function normalizeBoxSpacingStyleValueForWrite(value: unknown): unknown {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return Object.fromEntries(
            Object.entries(value).map(
                ([
                    breakpoint,
                    entryValue,
                ]) => [
                    breakpoint,
                    normalizeBoxSpacingCSSValue(entryValue),
                ],
            ),
        );
    }

    return normalizeBoxSpacingCSSValue(value);
}

/**
 * @private
 * @sw-package discovery
 */
export function serializeBoxSpacing(sides: BoxSpacingSides, options: SerializeBoxSpacingOptions = {}): string {
    const hasAnyInput = [
        sides.top,
        sides.right,
        sides.bottom,
        sides.left,
    ].some((side) => side.trim() !== '');

    if (!hasAnyInput) {
        return '';
    }

    const top = normalizeBoxSpacingSide(sides.top);
    const right = normalizeBoxSpacingSide(sides.right);
    const bottom = normalizeBoxSpacingSide(sides.bottom);
    const left = normalizeBoxSpacingSide(sides.left);

    if (top === '0' && right === '0' && bottom === '0' && left === '0') {
        return '';
    }

    const explicit = options.explicit ?? false;

    if (explicit) {
        return `${top} ${right} ${bottom} ${left}`;
    }

    if (top === right && top === bottom && top === left) {
        return top;
    }

    const linked = options.linked ?? false;

    if (linked) {
        if (top === bottom && right === left) {
            return `${top} ${right}`;
        }

        if (right === left) {
            return `${top} ${right} ${bottom}`;
        }
    }

    return `${top} ${right} ${bottom} ${left}`;
}
