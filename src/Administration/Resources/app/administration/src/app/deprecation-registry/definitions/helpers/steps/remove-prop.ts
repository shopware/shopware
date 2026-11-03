/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, PropReplacement, RemovePropStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Removes a deprecated prop without replacement, or rewrites it with `replaceWith` when the new
 * component expresses the same thing differently.
 *
 * @example
 * removeProp({ prop: 'is-invalid' });
 * removeProp({ prop: 'router-link', fix: 'unsafe-auto', replaceWith: routerLinkClickHandler });
 */
export function removeProp({ fix = 'auto', ...config }: StepConfig<RemovePropStep>): RemovePropStep {
    return defineUsageStep<RemovePropStep>({ kind: 'remove-prop', fix, ...config }, reportRemoveProp);
}

function reportRemoveProp(api: ComponentUsageRuleApi, step: RemovePropStep): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, step.prop);

    if (!attribute) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        ...planRemoval(api, step, attribute),
    });
}

function planRemoval(api: ComponentUsageRuleApi, step: RemovePropStep, attribute: TemplateNode): UsagePlan {
    const replacement: PropReplacement | undefined = step.replaceWith;

    if (hasObjectVBind(api)) {
        return {
            manualReason:
                replacement?.objectVBindReason ??
                `Object v-bind can hide this prop. Review the bound object and remove "${step.prop}" manually if needed.`,
        };
    }

    if (replacement) {
        return {
            produces: replacement.produces,
            fix: (fixer) => replacement.fix(api, attribute, fixer),
        };
    }

    return { fix: (fixer) => fixer.remove(attribute) };
}
