/**
 * @sw-package framework
 */

import type {
    ComponentMemberStep,
    ComponentReferenceStep,
    ConfigFileStep,
    FixLevel,
    ImportSourceStep,
    InjectKeyStep,
    MemberCallStep,
    MemberUsageStep,
    ObjectKeyStep,
    RemoveAssetStep,
    RemoveSnippetKeyStep,
    RemoveTemplateBlockStep,
    RenameCallStep,
    RenameComponentStep,
    RenamePackageStep,
    RenameSnippetKeyStep,
    RenameTemplateBlockStep,
    RenameTemplateEventStep,
    ReplaceExtensionStep,
    ReplaceObjectOptionStep,
    ServiceMethodStep,
    StepConfig,
    StoreMemberStep,
    StringValueStep,
} from '../types';

type SimpleStep =
    | ComponentMemberStep
    | ComponentReferenceStep
    | ConfigFileStep
    | ImportSourceStep
    | InjectKeyStep
    | MemberCallStep
    | MemberUsageStep
    | ObjectKeyStep
    | ServiceMethodStep
    | StoreMemberStep
    | StringValueStep
    | RenameSnippetKeyStep
    | RemoveAssetStep
    | RemoveSnippetKeyStep
    | RemoveTemplateBlockStep
    | RenameCallStep
    | RenameComponentStep
    | RenamePackageStep
    | RenameTemplateBlockStep
    | RenameTemplateEventStep
    | ReplaceExtensionStep
    | ReplaceObjectOptionStep;

/**
 * Creates the builder of a step that is plain data, matched and fixed by a dedicated ESLint rule or script.
 *
 * @example
 * const renamePackage = simpleStep<RenamePackageStep>('rename-package', 'auto');
 * renamePackage({ from: '@a/old', to: '@a/new' }); // { kind: 'rename-package', fix: 'auto', from: '@a/old', to: '@a/new' }
 */
function simpleStep<Step extends SimpleStep>(kind: Step['kind'], defaultFix: FixLevel) {
    return ({ fix = defaultFix, ...config }: StepConfig<Step>): Step => {
        return { kind, fix, ...config } as Step;
    };
}

/**
 * Replaces a deprecated component with its successor; `no-deprecated-components` converts the element.
 * Put it first in `onConvert`: the steps after it carry the old component's behaviour over.
 *
 * - converted together with the `onConvert` steps; the run level decides whether an element whose
 *   steps need manual work is converted
 * - `fix: 'manual'` → only reported, with `message` as the hint
 *
 * @example
 * renameComponent({ from: 'sw-loader', to: 'mt-loader' });
 */
export const renameComponent = simpleStep<RenameComponentStep>('rename-component', 'auto');

/**
 * Renames a deprecated global template function call.
 *
 * @example
 * renameCall({ from: '$tc', to: '$t' });
 */
export const renameCall = simpleStep<RenameCallStep>('rename-call', 'unsafe-auto');

/**
 * Replaces a deprecated component option with the given source.
 *
 * @example
 * replaceObjectOption({ from: 'metaInfo', to: 'metaInfo() { return {}; }' });
 */
export const replaceObjectOption = simpleStep<ReplaceObjectOptionStep>('replace-object-option', 'auto');

/**
 * Reports calls of a deprecated JavaScript member; `to` names the replacement in the report.
 *
 * @example
 * memberCall({ from: 'this.computePath', to: 'this.$el.contains(event.target)' });
 */
export const memberCall = simpleStep<MemberCallStep>('member-call', 'manual');

/**
 * Points imports of deprecated asset files to the converted file with another extension.
 *
 * @example
 * replaceExtension({ from: ['.png', '.jpg'], to: '.webp' });
 */
export const replaceExtension = simpleStep<ReplaceExtensionStep>('replace-extension', 'auto');

const removeAssetStep = simpleStep<RemoveAssetStep>('remove-asset', 'manual');

/**
 * Marks an asset file as deprecated without a replacement; the image-deprecation vite plugin
 * warns on every import of it. Use `replaceExtension` instead when a converted file exists.
 *
 * @example
 * usage: [removeAsset()]
 */
export function removeAsset(config: StepConfig<RemoveAssetStep> = {}): RemoveAssetStep {
    return removeAssetStep(config);
}

/**
 * Reports overrides of a Twig block that was removed without replacement.
 *
 * @example
 * removeTemplateBlock({ from: 'sw_mail_template_detail_preview_modal_footer' });
 */
export const removeTemplateBlock = simpleStep<RemoveTemplateBlockStep>('remove-template-block', 'manual');

/**
 * Renames overrides of a renamed Twig block.
 *
 * @example
 * renameTemplateBlock({ from: 'sw_condiiton_date_range_field_to_date', to: 'sw_condition_date_range_field_to_date' });
 */
export const renameTemplateBlock = simpleStep<RenameTemplateBlockStep>('rename-template-block', 'auto');

/**
 * Renames listeners of a renamed event on one component.
 *
 * @example
 * templateEventUsage({ component: 'sw-text-field', from: 'input', to: 'update:value' });
 */
export const templateEventUsage = simpleStep<RenameTemplateEventStep>('rename-template-event', 'auto');

/**
 * Reports usage of a removed snippet key and its children.
 *
 * @example
 * removeSnippetKey({ from: 'global.sw-condition.condition.dayOfWeekRule' });
 */
export const removeSnippetKey = simpleStep<RemoveSnippetKeyStep>('remove-snippet-key', 'manual');

/**
 * Replaces usage of a renamed snippet key.
 *
 * @example
 * renameSnippetKey({ from: 'global.sw-condition.condition.cartTaxDisplay.net', to: 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net' });
 */
export const renameSnippetKey = simpleStep<RenameSnippetKeyStep>('rename-snippet-key', 'auto');

/**
 * Reports a JavaScript API member wherever it is read, called or destructured; `no-deprecated-api-usage` runs it.
 *
 * @example
 * memberUsage({ from: 'Shopware.State', to: 'Shopware.Store' });
 */
export const memberUsage = simpleStep<MemberUsageStep>('member-usage', 'manual');

/**
 * Reports a method of an injected service.
 *
 * @example
 * serviceMethod({ service: 'integrationService', method: 'updateAdmin', to: "repositoryFactory.create('integration').save()" });
 */
export const serviceMethod = simpleStep<ServiceMethodStep>('service-method', 'manual');

/**
 * Reports a member of a Pinia store read through `Shopware.Store.get()`.
 *
 * @example
 * storeMember({ store: 'swProductDetail', member: 'productStates', to: 'productType' });
 */
export const storeMember = simpleStep<StoreMemberStep>('store-member', 'manual');

/**
 * Reports a member a core component loses in overrides and extensions of the component.
 * With `replaceInScript`, an `auto` step rewrites script reads of the member.
 *
 * @example
 * componentMember({ components: ['sw-order-list'], member: 'dateFilter', replaceInScript: "Shopware.Filter.getByName('date')", fix: 'auto' });
 */
export const componentMember = simpleStep<ComponentMemberStep>('component-member', 'manual');

/**
 * Reports an injected key that is no longer provided.
 *
 * @example
 * injectKey({ from: 'swOrderDetailOnLoadingChange', to: "Shopware.Store.get('swOrderDetail').setLoading()" });
 */
export const injectKey = simpleStep<InjectKeyStep>('inject-key', 'manual');

/**
 * Reports overrides, extensions, tags and string literals of a removed component.
 *
 * @example
 * componentReference({ component: 'sw-help-sidebar', to: 'sw-help-center-v2' });
 */
export const componentReference = simpleStep<ComponentReferenceStep>('component-reference', 'manual');

/**
 * Reports a string literal with this exact value; an `auto` step with `to` replaces it.
 *
 * @example
 * stringValue({ from: 'is-physical', to: 'physical' });
 */
export const stringValue = simpleStep<StringValueStep>('string-value', 'manual');

/**
 * Reports a key of an object literal.
 *
 * @example
 * objectKey({ from: 'cancelToken', to: 'signal' });
 */
export const objectKey = simpleStep<ObjectKeyStep>('object-key', 'manual');

/**
 * Reports an import or `require()` of a module, optionally only of some named specifiers.
 *
 * @example
 * importSource({ from: 'axios', specifiers: ['CancelToken'], to: 'AbortController' });
 */
export const importSource = simpleStep<ImportSourceStep>('import-source', 'manual');

/**
 * Reports a file whose name matches `pattern`, e.g. a build config.
 *
 * @example
 * configFile({ pattern: '^vite\\.config\\.[cm]?[jt]s$' });
 */
export const configFile = simpleStep<ConfigFileStep>('config-file', 'manual');

/**
 * Rewrites imports of a renamed npm package, keeping the import subpath.
 *
 * @example
 * renamePackage({ from: '@shopware-ag/admin-extension-sdk', to: '@shopware-ag/meteor-admin-sdk' });
 */
export const renamePackage = simpleStep<RenamePackageStep>('rename-package', 'auto');
