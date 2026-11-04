/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, FixLevel, MapPropValueStep, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/** Builder input of `mapPropValue`; a value without replacement can only be reported for a manual migration. */
export type MapPropValueConfig = {
    prop: string;
    from: string;
    message?: string;
} & ({ to: string; fix?: FixLevel; addBooleanProp?: string } | { to?: never; fix: 'manual'; addBooleanProp?: never });

/**
 * Replaces a deprecated static value of a prop. `addBooleanProp` adds the boolean prop the new value
 * needs to keep the old look, for example `ghost` for the former `variant="ghost"`.
 *
 * @example
 * mapPropValue({ prop: 'variant', from: 'warning', to: 'attention' });
 * mapPropValue({ prop: 'variant', from: 'ghost', to: 'primary', addBooleanProp: 'ghost' });
 * mapPropValue({ prop: 'variant', from: 'contrast', fix: 'manual', message: 'No replacement.' });
 */
export function mapPropValue({ fix = 'auto', ...config }: MapPropValueConfig): MapPropValueStep {
    return defineUsageStep<MapPropValueStep>({ kind: 'map-prop-value', fix, ...config }, reportMapPropValue);
}

function reportMapPropValue(api: ComponentUsageRuleApi, step: MapPropValueStep): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, step.prop);

    if (!attribute) {
        return;
    }

    if (api.ast.getDirectiveName(attribute) === 'bind') {
        reportBoundValue(api, step, attribute);

        return;
    }

    if (attribute.value?.value !== step.from) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        replacement: step.to,
        ...planValueMapping(api, step, attribute),
    });
}

/**
 * Flags a bound expression that contains the deprecated value as a string literal, for example
 * `:variant="hasError ? 'error' : 'info'"`. Only a developer can rewrite the expression; expressions
 * without the literal are not reported, because their runtime value is unknown.
 */
function reportBoundValue(api: ComponentUsageRuleApi, step: MapPropValueStep, attribute: TemplateNode): void {
    const source = api.ast.getAttributeValueSource(attribute) ?? '';

    if (!containsStringLiteral(source, step.from)) {
        return;
    }

    const replacementHint = step.to === undefined ? '' : ` with "${step.to}"`;

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        replacement: step.to,
        manualReason: `The bound expression can pass the deprecated value "${step.from}". Replace it${replacementHint} in the expression manually.`,
    });
}

/**
 * Tells whether a JavaScript expression source contains the value as a quoted string literal.
 *
 * @example
 * containsStringLiteral("isError ? 'error' : 'info'", 'error'); // true
 * containsStringLiteral('variant', 'error'); // false
 */
function containsStringLiteral(source: string, value: string): boolean {
    const escapedValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return new RegExp(`(['"\`])${escapedValue}\\1`).test(source);
}

function planValueMapping(api: ComponentUsageRuleApi, step: MapPropValueStep, attribute: TemplateNode): UsagePlan {
    const { to, addBooleanProp } = step;

    if (to === undefined) {
        return {};
    }

    if (addBooleanProp === undefined) {
        return { fix: (fixer) => fixer.replaceText(attribute.value, `"${to}"`) };
    }

    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide this prop value. Review the bound object and add "${addBooleanProp}" manually if needed.`,
        };
    }

    return {
        fix: (fixer) => {
            const valueFix = fixer.replaceText(attribute.value, `"${to}"`);

            if (api.ast.hasMatchingPropAttribute(api.node, addBooleanProp)) {
                return valueFix;
            }

            return [
                valueFix,
                fixer.insertTextAfterRange(attribute.range, ` ${addBooleanProp}`),
            ];
        },
    };
}
