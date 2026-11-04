/**
 * @sw-package framework
 */

import type {
    ComponentUsageReport,
    ComponentUsageRuleApi,
    ComponentUsageStep,
    ProducedAttribute,
    TemplateNode,
} from './types';

/**
 * How a step handles one usage: a fix, or the reason the usage needs a manual migration.
 * A plan with neither reports the usage without a fix.
 */
export type UsagePlan = {
    fix?: ComponentUsageReport['fix'];
    produces?: ProducedAttribute;
    /** Appended to the report and suppresses the fix. */
    manualReason?: string;
};

type UsageReport = UsagePlan & {
    /** The node the report points at. */
    node: TemplateNode;
    /** The deprecated prop, event, slot or model argument, named in the default headline. */
    apiName: string;
    /** The replacement named in the default headline. */
    replacement?: string;
    /** Replaces the default headline when "The "<api>" API is deprecated." does not describe the usage. */
    headline?: string;
};

/**
 * Reports one deprecated usage of the running step. Builds the headline from the step's `message` or the
 * deprecated API name, appends the manual reason, and only passes the fix on when the step and the rule
 * options allow an automatic fix. Every component usage step reports through it.
 *
 * @example
 * reportUsage(api, {
 *     node: attribute,
 *     apiName: 'value',
 *     replacement: 'model-value',
 *     fix: (fixer) => fixer.replaceText(attribute.key, 'model-value'),
 * });
 * // [mt-text-field] The "value" API is deprecated. Use "model-value" instead.
 */
export function reportUsage(api: ComponentUsageRuleApi, report: UsageReport): void {
    const { node, apiName, replacement, manualReason, produces, fix } = report;
    const headline = api.usage.message ?? report.headline ?? defaultHeadline(apiName, replacement);
    const text = manualReason ? `${headline}\n${manualReason}` : headline;

    api.report({
        node,
        message: api.buildMessage(`[${api.node.name}] ${text}`),
        produces,
        fix: manualReason === undefined && api.canAutoFix() ? fix : undefined,
    });
}

function defaultHeadline(apiName: string, replacement: string | undefined): string {
    const replacementHint = replacement ? ` Use "${replacement}" instead.` : '';

    return `The "${apiName}" API is deprecated.${replacementHint}`;
}

/**
 * Tells whether the element binds an object with `v-bind="…"`, which can hide or override any prop,
 * so prop fixes on it must fall back to a manual review.
 *
 * @example
 * hasObjectVBind(api); // true for <mt-button v-bind="buttonProps" />
 */
export function hasObjectVBind(api: ComponentUsageRuleApi): boolean {
    return Boolean(api.ast.findObjectVBindAttribute(api.node));
}

/**
 * Tells whether the element binds listeners with `v-on="…"`, which can hide or override any event,
 * so event fixes on it must fall back to a manual review.
 *
 * @example
 * hasObjectVOn(api); // true for <mt-text-field v-on="listeners" />
 */
export function hasObjectVOn(api: ComponentUsageRuleApi): boolean {
    return Boolean(api.ast.findObjectVOnAttribute(api.node));
}

/**
 * Attaches the ESLint reporter to a component usage step, so the reporter receives the typed step.
 * Every component usage builder ends with it.
 *
 * @example
 * return defineUsageStep<RemoveEventStep>({ kind: 'remove-event', fix, ...config }, reportRemoveEvent);
 */
export function defineUsageStep<Step extends ComponentUsageStep>(
    fields: Omit<Step, 'eslint'>,
    report: (api: ComponentUsageRuleApi, step: Step) => void,
): Step {
    const step = {
        ...fields,
        eslint: {
            report: (api: ComponentUsageRuleApi) => report(api, step),
        },
    } as Step;

    return step;
}
