/**
 * @sw-package framework
 *
 * Where a step reads a value of the old usage from. `propFromSource()` takes `fromSlot()`, `fromProp()`
 * and `firstFoundOf()`; `childElementsToArrayProp()` takes `fromText()` and `fromAttribute()` for the
 * fields of each array item.
 */

import type { FirstFoundOf, ItemFieldSource, PropSource } from '../types';

/**
 * The content of a slot: its text, or with `childElement` an attribute of the single child element of
 * one of those names. The default slot also counts content written without a slot template.
 *
 * @example
 * fromSlot('default'); // <sw-empty-state>Hi</sw-empty-state> → 'Hi'
 * fromSlot('icon', { childElement: ['mt-icon', 'sw-icon'], attribute: 'name' });
 * // <template #icon><mt-icon name="regular-rocket" /></template> → 'regular-rocket'
 */
export function fromSlot(slot: string, options: { childElement?: string[]; attribute?: string } = {}): PropSource {
    return { from: 'slot', slot, ...options };
}

/**
 * A prop of the old usage, static or bound.
 *
 * @example
 * fromProp('subline'); // <sw-empty-state :subline="text" /> → text
 */
export function fromProp(prop: string): PropSource {
    return { from: 'prop', prop };
}

/**
 * Tries the sources in order and takes the first one the element uses. An empty slot counts as not
 * used, because Vue renders its fallback content then.
 *
 * @example
 * firstFoundOf([fromSlot('default'), fromProp('subline')]);
 * // <sw-empty-state subline="Sub">Text</sw-empty-state> → 'Text'
 * // <sw-empty-state subline="Sub" /> → 'Sub'
 */
export function firstFoundOf(sources: PropSource[]): FirstFoundOf {
    return { from: 'first-found-of', sources };
}

/**
 * The text of the child element, with mustaches turned into code. Markup in it needs a manual move.
 *
 * @example
 * fromText(); // <option>{{ $t('a') }}</option> → $t('a'); <option>A</option> → 'A'
 */
export function fromText(): ItemFieldSource {
    return { from: 'text' };
}

/**
 * An attribute of the child element, static or bound. A valueless attribute such as `disabled` is
 * `true`; an unset attribute leaves the field out, or needs a manual move when it is `required`.
 *
 * @example
 * fromAttribute('value', { required: true, emptyAsNull: true });
 * // <option value="a"> → 'a'; <option value=""> → null; <option> → manual
 * fromAttribute('disabled'); // <sw-tabs-item disabled> → true
 * fromAttribute('value', { asNumber: true }); // <option value="1"> → 1; <option value="a"> → manual
 */
export function fromAttribute(
    attribute: string,
    options: { required?: boolean; emptyAsNull?: boolean; boundReview?: string; asNumber?: boolean } = {},
): ItemFieldSource {
    return { from: 'attribute', attribute, ...options };
}
