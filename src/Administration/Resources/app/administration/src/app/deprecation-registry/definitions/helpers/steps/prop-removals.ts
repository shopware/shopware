/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, RemovePropStep, ReplacePropStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Removes a deprecated prop that has no replacement.
 *
 * @example
 * removeProp({ prop: 'is-invalid' });
 */
export function removeProp({ fix = 'auto', ...config }: StepConfig<RemovePropStep>): RemovePropStep {
    return defineUsageStep<RemovePropStep>({ kind: 'remove-prop', fix, ...config }, (api, step) => {
        reportPropAttribute(api, step.prop, (attribute) => planRemoval(api, step, attribute));
    });
}

/**
 * Rewrites a deprecated prop into what the new component uses instead, as described by a `PropReplacement`.
 *
 * @example
 * replaceProp({ prop: 'router-link', with: routerLinkClickHandler, fix: 'unsafe-auto' });
 * // router-link="sw.dashboard.index" → @click="$router.push('sw.dashboard.index')"
 */
export function replaceProp({ fix = 'auto', ...config }: StepConfig<ReplacePropStep>): ReplacePropStep {
    return defineUsageStep<ReplacePropStep>({ kind: 'replace-prop', fix, ...config }, (api, step) => {
        reportPropAttribute(api, step.prop, (attribute) => planReplacement(api, step, attribute));
    });
}

function reportPropAttribute(api: ComponentUsageRuleApi, prop: string, plan: (attribute: TemplateNode) => UsagePlan): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, prop);

    if (!attribute) {
        return;
    }

    reportUsage(api, { node: attribute, apiName: prop, ...plan(attribute) });
}

function planRemoval(api: ComponentUsageRuleApi, step: RemovePropStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide this prop. Review the bound object and remove "${step.prop}" manually if needed.`,
        };
    }

    return { fix: (fixer) => api.removeAttribute(fixer, attribute) };
}

function planReplacement(api: ComponentUsageRuleApi, step: ReplacePropStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return { manualReason: step.with.objectVBindReason };
    }

    return {
        produces: step.with.produces,
        fix: (fixer) => step.with.fix(api, attribute, fixer),
    };
}
