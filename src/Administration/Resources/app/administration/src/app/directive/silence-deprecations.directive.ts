/**
 * @sw-package framework
 */

import { getCurrentInstance, type Directive } from 'vue';
import { silenceKey } from 'src/core/feature';

/**
 * Silences the deprecations of a major for the component it sits on, the template counterpart of
 * `Feature.silence()`: the component itself, the deprecated props passed to it and what it reports while it is
 * created or rendered. What it renders, its slot content included, still reports. Use it on the pre-major side of a
 * `v-if` that keeps a deprecated component or prop until the major. It does nothing on a `<template>`, which Vue
 * renders as a fragment.
 *
 * The directive itself has no hooks: `Feature.isSilenced()` looks it up on the vnode of the running component.
 * Once the major is active nothing is silenced, so a use outside of its branch still throws.
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_DEPRECATION_SILENCE
 * @example
 * <sw-loader v-if="!feature.isActive('V6_8_0_0')" v-silence-deprecations="'V6_8_0_0'" />
 * <mt-loader v-else />
 */
const silenceDeprecations: Directive<HTMLElement, string> = {};

Shopware.Directive.register('silence-deprecations', silenceDeprecations);

/**
 * Whether the running component carries `v-silence-deprecations` for `majorFlag`, spelled as `silenceKey()` spells it.
 * It compares with the registered directive, as the registry keeps the first one when this module runs again.
 *
 * @example
 * isSilencedByTemplate('V6_8_0_0'); // true while `<sw-loader v-silence-deprecations="'V6_8_0_0'" />` renders
 */
function isSilencedByTemplate(majorFlag: string): boolean {
    const instance = getCurrentInstance();
    const registered = Shopware.Directive.getByName('silence-deprecations');
    // Vue copies the directives of a component onto its root, so a root component finds them on its parent too
    const inherited = instance?.parent?.vnode.dirs ?? [];

    return (
        instance?.vnode.dirs?.some(
            (binding) =>
                binding.dir === registered &&
                !inherited.includes(binding) &&
                typeof binding.value === 'string' &&
                silenceKey(binding.value) === majorFlag,
        ) ?? false
    );
}

Shopware.Feature.setSilenceCheck('v-silence-deprecations', isSilencedByTemplate);

/**
 * @private
 */
export {};
