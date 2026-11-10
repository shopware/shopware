/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, RemovePropStep, ReplacePropStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Removes a deprecated prop that has no replacement.
 *
 * - set → removed
 * - an object `v-bind` on the element → manual
 * - `reviewWhenTrue`, for a boolean prop whose "true" behaviour the new component lost → review
 *   comment wherever the prop was or could be true; bound to `false` → removed without one
 * - `trueByDefault`, for a prop the old component treated as true when unset → the review comment
 *   also on converted elements that did not set it
 * - `reviewWhenFalse`, for a boolean prop whose "false" behaviour the new component lost → review
 *   comment unless the prop is a valueless attribute, `="true"` or bound to `true`, also on converted
 *   elements that did not set it
 *
 * @example
 * removeProp({ prop: 'is-invalid' });
 * // <mt-x is-invalid /> → <mt-x />
 * removeProp({ prop: 'absolute', trueByDefault: true, reviewWhenTrue: 'It covered its container: check the spacing.' });
 * // <sw-empty-state /> → <mt-empty-state /> + review comment
 * // <sw-empty-state :absolute="false" /> → <mt-empty-state />
 * removeProp({ prop: 'local-mode', reviewWhenFalse: 'The new select never saves on its own.' });
 * // <sw-x local-mode /> → <mt-x />; <sw-x /> → <mt-x /> + review comment
 */
export function removeProp({ fix = 'auto', ...config }: StepConfig<RemovePropStep>): RemovePropStep {
    return defineUsageStep<RemovePropStep>({ kind: 'remove-prop', fix, ...config }, (api, step) => {
        const isSet = api.ast.hasMatchingPropAttribute(api.node, step.prop);
        // Once converted, an unset prop could also be one this step removed, so only the old element tells
        const isOldElement = api.node.name === api.migration.component;

        const reviewWhenUnset = step.trueByDefault ? step.reviewWhenTrue : step.reviewWhenFalse;

        if (!isSet && reviewWhenUnset && isOldElement) {
            reportUsage(api, {
                node: api.node,
                apiName: step.prop,
                headline: `The "${step.prop}" prop is not set.`,
                reviewReason: reviewWhenUnset,
                conversionNote: true,
            });

            return;
        }

        reportPropAttribute(api, step.prop, (attribute) => planRemoval(api, step, attribute));
    });
}

/**
 * Rewrites a deprecated prop into what the new component uses instead, as described by a `PropReplacement`.
 *
 * - set → rewritten by `with.fix`
 * - an object `v-bind` on the element → manual with `with.objectVBindReason`
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

function reportPropAttribute(
    api: ComponentUsageRuleApi,
    prop: string,
    plan: (attribute: TemplateNode) => UsagePlan & { headline?: string },
): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, prop);

    if (!attribute) {
        return;
    }

    reportUsage(api, { node: attribute, apiName: prop, ...plan(attribute) });
}

function planRemoval(
    api: ComponentUsageRuleApi,
    step: RemovePropStep,
    attribute: TemplateNode,
): UsagePlan & { headline?: string } {
    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide this prop. Review the bound object and remove "${step.prop}" manually if needed.`,
        };
    }

    const isBound = api.ast.getDirectiveName(attribute) === 'bind';
    const value = api.ast.getAttributeValueSource(attribute);
    const isFalse = isBound && value === 'false';
    const isTrue = isBound ? value === 'true' : [null, '', 'true'].includes(value);
    const reviewReason = (!isFalse && step.reviewWhenTrue) || (!isTrue && step.reviewWhenFalse) || undefined;

    return {
        fix: (fixer) => api.removeAttribute(fixer, attribute),
        ...(reviewReason ? { headline: `The "${step.prop}" prop is removed.`, reviewReason } : {}),
    };
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
