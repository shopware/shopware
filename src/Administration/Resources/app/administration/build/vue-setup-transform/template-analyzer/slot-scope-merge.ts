/**
 * @sw-package framework
 */

/**
 * Rewrites the default slot scope of an extended sw-block.
 *
 * The override transform injects public bindings and the private `__swOverride` namespace into the
 * `#default` destructure. These helpers merge the generated entries into whatever object pattern the
 * author already wrote, and reject the slot-scope shapes the invisible injection would silently break
 * (the reserved `__swOverride` key and catch-all bindings).
 */

import type { Node as BabelNode } from '@babel/types';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { parseBindingPattern } from './expression-references';
import type { DirectiveNode, ElementNode, SlotMapping, TemplateEdit } from './template-references';

const RESERVED_OVERRIDE_STATE_NAME = '__swOverride';

/**
 * Checks whether a slot binding pattern declares or reads the machine-owned override-private state key.
 *
 * e.g. `#default="{ __swOverride }"`, `#default="{ nested: { __swOverride } }"`, or
 * `#default="{ ...__swOverride }"` all collide with the generated private state channel.
 */
function hasReservedOverrideSlotBinding(pattern: BabelNode | null | undefined): boolean {
    if (!pattern) {
        return false;
    }

    if (pattern.type === 'Identifier') {
        return pattern.name === RESERVED_OVERRIDE_STATE_NAME;
    }

    if (pattern.type === 'RestElement') {
        return hasReservedOverrideSlotBinding(pattern.argument);
    }

    if (pattern.type === 'AssignmentPattern') {
        return hasReservedOverrideSlotBinding(pattern.left);
    }

    if (pattern.type === 'ArrayPattern') {
        return pattern.elements.some(hasReservedOverrideSlotBinding);
    }

    if (pattern.type === 'ObjectPattern') {
        return pattern.properties.some((property) => {
            if (property.type === 'RestElement') {
                return hasReservedOverrideSlotBinding(property.argument);
            }

            if (getObjectPatternSourceKey(property) === RESERVED_OVERRIDE_STATE_NAME) {
                return true;
            }

            return hasReservedOverrideSlotBinding(property.value);
        });
    }

    return false;
}

/**
 * Rejects user-authored slot bindings that would collide with the generated override-private state channel.
 *
 */
function assertNoReservedOverrideSlotScope(slotDirective: DirectiveNode | undefined): void {
    if (!slotDirective?.exp?.content) {
        return;
    }

    try {
        const { pattern } = parseBindingPattern(slotDirective.exp.content);

        if (!hasReservedOverrideSlotBinding(pattern)) {
            return;
        }
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
        return;
    }

    throw new ShopwareSetupTransformError(
        '"__swOverride" is reserved for Shopware override-private state and must not be used as a slot-scope binding.',
        0,
    );
}

/**
 * Rejects catch-all default slot scopes on an extended block: a bare identifier (`slotProps`) or a rest
 * element (`{ ...rest }`).
 *
 * The override transform injects override state as named bindings into this slot scope. Because the
 * injection is invisible to the author, a catch-all binding would silently stop capturing whatever the
 * transform pulls out - and what it pulls out depends on which bindings the template happens to
 * reference elsewhere. Requiring explicit named bindings keeps what the author reads out predictable.
 */
function assertNoCatchAllSlotScope(slotDirective: DirectiveNode | undefined): void {
    if (!slotDirective?.exp?.content) {
        return;
    }

    let pattern;
    try {
        pattern = parseBindingPattern(slotDirective.exp.content).pattern;
    } catch {
        // Invalid or unsupported patterns are handled by Vue's own template parser/compiler.
        return;
    }

    if (pattern.type === 'Identifier') {
        throw new ShopwareSetupTransformError(
            'A bare identifier default slot scope (for example #default="slotProps") is not supported in a ' +
                '<sw-block extends="..."> block. The override transform injects override state into this slot scope, ' +
                'so binding the whole scope to one name would silently change what it contains. Destructure the slot ' +
                'props you need by name instead.',
            0,
        );
    }

    if (pattern.type === 'ObjectPattern' && pattern.properties.some((property) => property.type === 'RestElement')) {
        throw new ShopwareSetupTransformError(
            'A rest element (...) is not supported in a <sw-block extends="..."> default slot scope. The override ' +
                'transform injects override state into this slot scope, so a rest binding would silently exclude the ' +
                'injected bindings. Destructure the slot props you need by name instead.',
            0,
        );
    }
}

/**
 * Returns the insertion point before the closing angle bracket of an opening tag.
 *
 */
function findOpeningTagAttributeEnd(template: string, elementStart: number): number {
    let quote: '"' | "'" | null = null;

    for (let index = elementStart; index < template.length; index += 1) {
        const character = template[index];

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
            continue;
        }

        if (character === '>') {
            return template[index - 1] === '/' ? index - 1 : index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate <sw-block> opening tag end.', elementStart);
}

/**
 * Returns the insertion point immediately after an opening tag name.
 *
 */
function findOpeningTagNameEnd(template: string, elementStart: number): number {
    for (let index = elementStart + 1; index < template.length; index += 1) {
        const character = template[index];

        if (/\s|\/|>/.test(character)) {
            return index;
        }
    }

    throw new ShopwareSetupTransformError('Unable to locate <sw-block> tag name end.', elementStart);
}

/**
 * Formats the private slot-scope destructuring entry for one override file namespace.
 *
 */
function createPrivateSlotMapping(namespace: string, localNames: string[]): SlotMapping {
    return {
        sourceKey: '__swOverride',
        source: `__swOverride: { ${namespace}: { ${localNames.join(', ')} } }`,
    };
}

/**
 * Extracts the source property name from an existing object pattern entry.
 *
 * e.g. `{ body }` and `{ body: alias }` both read slot prop `body`; `{ [key]: alias }` reads a
 * computed key and returns null.
 */
function getObjectPatternSourceKey(property: BabelNode | null | undefined): string | null {
    if (!property || property.type !== 'ObjectProperty' || property.computed) {
        return null;
    }

    if (property.key.type === 'Identifier') {
        return property.key.name;
    }

    if (property.key.type === 'StringLiteral') {
        return property.key.value;
    }

    return null;
}

/**
 * Merges generated mappings into an existing object destructuring slot expression.
 *
 */
function mergeObjectSlotExpression(expression: string, mappings: SlotMapping[]): string {
    const { pattern, offset } = parseBindingPattern(expression);

    if (pattern.type !== 'ObjectPattern') {
        throw new ShopwareSetupTransformError(
            'Shopware setup can only merge generated slot props into an object default slot scope.',
            0,
        );
    }

    const existingKeys = new Set(
        pattern.properties.map(getObjectPatternSourceKey).filter((key): key is string => Boolean(key)),
    );
    const newSources = mappings.filter((mapping) => !existingKeys.has(mapping.sourceKey)).map((mapping) => mapping.source);

    if (newSources.length === 0) {
        return expression;
    }

    const existingSources = pattern.properties.map((property) => {
        if (typeof property?.start !== 'number' || typeof property.end !== 'number') {
            throw new ShopwareSetupTransformError('Unable to merge slot scope without source ranges.', 0);
        }

        return expression.slice(property.start - offset, property.end - offset);
    });
    // Generated bindings are inserted before the first property that can read them (a default
    // value such as `{ x = fallback }`) and before any rest element. This lets existing defaults
    // reference an injected binding without a temporal-dead-zone while keeping the rest element
    // last, as object patterns require.
    const restIndex = pattern.properties.findIndex((property) => property.type === 'RestElement');
    const firstDefaultIndex = pattern.properties.findIndex(
        (property) => property.type === 'ObjectProperty' && property.value?.type === 'AssignmentPattern',
    );
    const insertionIndex = [
        restIndex,
        firstDefaultIndex,
    ]
        .filter((index) => index !== -1)
        .reduce((min, index) => Math.min(min, index), existingSources.length);
    const mergedSources = [
        ...existingSources.slice(0, insertionIndex),
        ...newSources,
        ...existingSources.slice(insertionIndex),
    ];

    return `{ ${mergedSources.join(', ')} }`;
}

/**
 * Merges generated mappings into any supported existing default slot scope.
 *
 */
function mergeSlotExpression(slotDirective: DirectiveNode | undefined, mappings: SlotMapping[]): string {
    const sources = mappings.map((mapping) => mapping.source);

    if (!slotDirective?.exp?.content) {
        return `{ ${sources.join(', ')} }`;
    }

    const expression = slotDirective.exp.content.trim();
    const { pattern } = parseBindingPattern(expression);

    if (pattern.type === 'ObjectPattern') {
        return mergeObjectSlotExpression(expression, mappings);
    }

    // Bare-identifier and rest slot scopes are rejected earlier by assertNoCatchAllSlotScope.
    throw new ShopwareSetupTransformError(
        'Shopware setup can only merge generated slot props into an object default slot scope.',
        0,
    );
}

/**
 * Builds the source replacement for one sw-block default slot directive.
 *
 */
function createSlotMergeEdit(
    block: ShopwareSetupBlock,
    node: ElementNode,
    slotDirective: DirectiveNode | undefined,
    mappings: SlotMapping[],
): TemplateEdit {
    if (!block.template) {
        throw new ShopwareSetupTransformError('Unable to merge slot scope without a template block.', 0);
    }

    const mergedExpression = mergeSlotExpression(slotDirective, mappings);

    if (!slotDirective) {
        const insertionPoint = findOpeningTagAttributeEnd(block.template.content, node.loc.start.offset);

        return {
            start: block.template.contentStart + insertionPoint,
            end: block.template.contentStart + insertionPoint,
            replacement: ` #default="${mergedExpression}"`,
        };
    }

    if (!slotDirective.exp) {
        return {
            start: block.template.contentStart + slotDirective.loc.start.offset,
            end: block.template.contentStart + slotDirective.loc.end.offset,
            replacement: `${slotDirective.rawName}="${mergedExpression}"`,
        };
    }

    return {
        start: block.template.contentStart + slotDirective.exp.loc.start.offset,
        end: block.template.contentStart + slotDirective.exp.loc.end.offset,
        replacement: mergedExpression,
    };
}

export {
    assertNoCatchAllSlotScope,
    assertNoReservedOverrideSlotScope,
    createPrivateSlotMapping,
    createSlotMergeEdit,
    findOpeningTagNameEnd,
    mergeObjectSlotExpression,
};
