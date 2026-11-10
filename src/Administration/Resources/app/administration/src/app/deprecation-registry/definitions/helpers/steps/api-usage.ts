/**
 * @sw-package framework
 *
 * Steps of `apiUsageMigration`: removed or changed Administration APIs that extension code uses.
 * `no-deprecated-api-usage` matches them. A `manual` step only gets a review comment with its
 * `message` at the `partial` and `manual` run levels; the `replace*` steps rewrite the code.
 */

import type {
    ApiUsageStep,
    ComponentMemberStep,
    FixLevel,
    ImportSourceStep,
    InjectKeyStep,
    MemberUsageStep,
    ModuleSnippetsStep,
    ObjectKeyStep,
    RemovedComponentStep,
    ReplaceComponentMemberStep,
    ReplaceComponentMethodCallStep,
    StepConfig,
    StringLiteralStep,
    TemplateAttributeStep,
} from '../types';

function apiUsageStep<Step extends ApiUsageStep>(kind: Step['kind'], defaultFix: FixLevel) {
    return ({ fix = defaultFix, ...config }: StepConfig<Step>): Step => {
        return { kind, fix, ...config } as Step;
    };
}

/**
 * Reports `<object>.<property>`, also when destructured; `object`, `service` or `store` names the receiver.
 *
 * @example
 * memberUsage({ object: 'Shopware', properties: ['State'], message: 'Use Shopware.Store instead.' });
 * memberUsage({ service: 'orderService', properties: ['toggleAutomaticPromotions'], message: '…' });
 * memberUsage({ store: 'swProductDetail', properties: ['productStates'], message: '…' });
 */
export const memberUsage = apiUsageStep<MemberUsageStep>('member-usage', 'manual');

/**
 * Reports a member a component lost wherever an override or extension of it uses it: `this.<name>`,
 * its own definition in the options, and template expressions.
 *
 * @example
 * componentMember({ components: ['sw-order-detail'], names: ['isLoading'], message: 'Use loading.order of the swOrderDetail store.' });
 */
export const componentMember = apiUsageStep<ComponentMemberStep>('component-member', 'manual');

/**
 * Rewrites `this.<name>` in overrides of the components to `with`; template uses get `templateMessage`.
 *
 * @example
 * replaceComponentMember({
 *     components: ['sw-order-list'],
 *     name: 'dateFilter',
 *     with: "Shopware.Filter.getByName('date')",
 *     templateMessage: 'Add a dateFilter computed that returns Shopware.Filter.getByName(\'date\').',
 * });
 */
export const replaceComponentMember = apiUsageStep<ReplaceComponentMemberStep>('replace-component-member', 'auto');

/**
 * Rewrites calls of a removed method; `{0}` in `with` is the first argument.
 *
 * @example
 * replaceComponentMethodCall({ components: ['sw-order-list'], name: 'getDelivery', with: '{0}.primaryOrderDelivery' });
 */
export const replaceComponentMethodCall = apiUsageStep<ReplaceComponentMethodCallStep>(
    'replace-component-method-call',
    'auto',
);

/**
 * Reports an injected key that no component provides anymore.
 *
 * @example
 * injectKey({ names: ['swOrderDetailOnLoadingChange'], message: 'Use the swOrderDetail store.' });
 */
export const injectKey = apiUsageStep<InjectKeyStep>('inject-key', 'manual');

/**
 * Reports overrides, extensions and template elements of removed components.
 *
 * @example
 * removedComponent({ names: ['sw-order-user-card'], message: 'Remove this override.' });
 */
export const removedComponent = apiUsageStep<RemovedComponentStep>('removed-component', 'manual');

/**
 * Reports imports of a removed module or package.
 *
 * @example
 * importSource({ sources: ['vuex'], message: 'Use Pinia stores.' });
 */
export const importSource = apiUsageStep<ImportSourceStep>('import-source', 'manual');

/**
 * Reports string literals with a removed value.
 *
 * @example
 * stringLiteral({ values: ['is-physical'], message: 'Use the product type "physical".' });
 */
export const stringLiteral = apiUsageStep<StringLiteralStep>('string-literal', 'manual');

/**
 * Reports a key of an object literal.
 *
 * @example
 * objectKey({ names: ['cancelToken'], message: 'Pass the signal of an AbortController.' });
 */
export const objectKey = apiUsageStep<ObjectKeyStep>('object-key', 'manual');

/**
 * Reports an attribute, prop binding or listener in templates.
 *
 * @example
 * templateAttribute({ elements: ['router-view'], names: ['is-editing'], components: ['sw-order-detail'], message: '…' });
 */
export const templateAttribute = apiUsageStep<TemplateAttributeStep>('template-attribute', 'manual');

const moduleSnippetsStep = apiUsageStep<ModuleSnippetsStep>('module-snippets', 'auto');

/**
 * Removes the `snippets` property of `Module.register()` with the imports of its snippet files.
 *
 * @example
 * usage: [moduleSnippets()]
 */
export function moduleSnippets(config: StepConfig<ModuleSnippetsStep> = {}): ModuleSnippetsStep {
    return moduleSnippetsStep(config);
}
