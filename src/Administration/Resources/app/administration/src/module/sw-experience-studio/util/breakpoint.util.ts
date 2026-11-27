/**
 * @sw-package discovery
 */

import type {
    ContentSystemBreakpoint,
    ContentSystemBreakpointFallbacks,
} from 'src/core/service/api/content-system-element-type.api.service';

/**
 * @private
 */
export function resolveBreakpointValue<Value>(
    values: Partial<Record<ContentSystemBreakpoint, Value>>,
    breakpoint: ContentSystemBreakpoint,
    fallbacks: ContentSystemBreakpointFallbacks,
): Value | undefined {
    const ownValue = values[breakpoint];
    if (ownValue != null) {
        return ownValue;
    }

    for (const fallback of fallbacks[breakpoint] ?? []) {
        const inheritedValue = values[fallback];
        if (inheritedValue != null) {
            return inheritedValue;
        }
    }

    return undefined;
}
