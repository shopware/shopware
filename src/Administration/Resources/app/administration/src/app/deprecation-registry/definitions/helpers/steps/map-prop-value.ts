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

    if (!attribute || api.ast.getDirectiveName(attribute) === 'bind' || attribute.value?.value !== step.from) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        replacement: step.to,
        ...planValueMapping(api, step, attribute),
    });
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
