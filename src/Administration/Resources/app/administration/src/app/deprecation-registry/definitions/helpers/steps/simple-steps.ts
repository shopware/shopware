/**
 * @sw-package framework
 */

import type {
    FixLevel,
    MemberCallStep,
    RemoveAssetStep,
    RemoveSnippetKeyStep,
    RemoveTemplateBlockStep,
    RenameCallStep,
    RenameComponentStep,
    RenamePackageStep,
    RenameTemplateBlockStep,
    RenameSnippetKeyStep,
    RenameTemplateEventStep,
    ReplaceAssetStep,
    ReplaceExtensionStep,
    ReplaceObjectOptionStep,
    StepConfig,
} from '../types';

type SimpleStep =
    | MemberCallStep
    | RemoveAssetStep
    | RemoveSnippetKeyStep
    | RemoveTemplateBlockStep
    | RenameCallStep
    | RenameComponentStep
    | RenamePackageStep
    | RenameSnippetKeyStep
    | RenameTemplateBlockStep
    | RenameTemplateEventStep
    | ReplaceAssetStep
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

/**
 * Points references of the migration's files to another file, for assets that have no converted twin
 * with the same name.
 *
 * @example
 * replaceAsset({ to: '/static/img/cms/preview_mountain_small.webp' });
 */
export const replaceAsset = simpleStep<ReplaceAssetStep>('replace-asset', 'auto');

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
 * Replaces string literals that are exactly a removed snippet key with its successor.
 *
 * @example
 * renameSnippetKey({ from: 'global.sw-condition.condition.cartTaxDisplay.net', to: 'global.sw-condition-generic.cartTaxDisplay.taxDisplay.options.net' });
 */
export const renameSnippetKey = simpleStep<RenameSnippetKeyStep>('rename-snippet-key', 'auto');

/**
 * Rewrites imports of a renamed npm package, keeping the import subpath.
 *
 * @example
 * renamePackage({ from: '@shopware-ag/admin-extension-sdk', to: '@shopware-ag/meteor-admin-sdk' });
 */
export const renamePackage = simpleStep<RenamePackageStep>('rename-package', 'auto');
