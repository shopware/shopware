/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, FixLevel, ReplacePropValueStep, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/** Builder input of `replacePropValue`; a value without replacement can only be reported for a manual migration. */
export type ReplacePropValueConfig = {
    prop: string;
    fromValue: string;
    message?: string;
} & ({ toValue: string; fix?: FixLevel; alsoAddProp?: string } | { toValue?: never; fix: 'manual'; alsoAddProp?: never });

/**
 * Replaces a deprecated static value of a prop. `alsoAddProp` adds the boolean prop the new value needs
 * to keep the old look.
 *
 * - static `fromValue` → `toValue`
 * - no `toValue` → reported for a manual migration with `message`
 * - a bound expression that contains `fromValue` as a string → manual
 * - any other bound expression on a converted element → review comment listing every deprecated value
 *
 * @example
 * replacePropValue({ prop: 'variant', fromValue: 'danger', toValue: 'critical' });
 * // <mt-button variant="danger"> → <mt-button variant="critical">
 * replacePropValue({ prop: 'variant', fromValue: 'ghost', toValue: 'primary', alsoAddProp: 'ghost' });
 * // <mt-button variant="ghost"> → <mt-button variant="primary" ghost>
 * replacePropValue({ prop: 'variant', fromValue: 'contrast', fix: 'manual', message: 'No replacement.' });
 * // <mt-button :variant="isError ? 'danger' : 'primary'"> → manual
 */
export function replacePropValue({ fix = 'auto', ...config }: ReplacePropValueConfig): ReplacePropValueStep {
    return defineUsageStep<ReplacePropValueStep>({ kind: 'replace-prop-value', fix, ...config }, reportReplacePropValue);
}

function reportReplacePropValue(api: ComponentUsageRuleApi, step: ReplacePropValueStep): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, step.prop);

    if (!attribute) {
        return;
    }

    if (api.ast.getDirectiveName(attribute) === 'bind') {
        reportBoundValue(api, step, attribute);

        return;
    }

    if (attribute.value?.value !== step.fromValue) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        replacement: step.toValue,
        ...planValueMapping(api, step, attribute),
    });
}

/**
 * Handles a bound value. An expression with the deprecated value as a string literal, for example
 * `:variant="hasError ? 'error' : 'info'"`, needs a manual rewrite. Any other expression except a plain
 * literal is only known at runtime: on a just converted element it gets a review comment that lists
 * every deprecated value of the prop, once per prop.
 */
function reportBoundValue(api: ComponentUsageRuleApi, step: ReplacePropValueStep, attribute: TemplateNode): void {
    const source = api.ast.getAttributeValueSource(attribute) ?? '';

    if (containsStringLiteral(source, step.fromValue)) {
        const replacementHint = step.toValue === undefined ? '' : ` with "${step.toValue}"`;

        reportUsage(api, {
            node: attribute,
            apiName: step.prop,
            replacement: step.toValue,
            manualReason: `The bound expression can pass the deprecated value "${step.fromValue}". Replace it${replacementHint} in the expression manually.`,
        });

        return;
    }

    const valueSteps = api.migration.usage.filter((usage): usage is ReplacePropValueStep => {
        return usage.kind === 'replace-prop-value' && usage.prop === step.prop;
    });
    const isLiteral = attribute.value?.expression?.type === 'Literal';

    if (isLiteral || valueSteps[0] !== step || !api.isConverted()) {
        return;
    }

    const deprecatedValues = valueSteps.map((valueStep) => `"${valueStep.fromValue}"`).join(', ');

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        headline: `The bound "${step.prop}" value is only known at runtime.`,
        reviewReason: `Make sure it never passes one of the deprecated values ${deprecatedValues}.`,
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

function planValueMapping(api: ComponentUsageRuleApi, step: ReplacePropValueStep, attribute: TemplateNode): UsagePlan {
    const { toValue: to, alsoAddProp: addBooleanProp } = step;

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
