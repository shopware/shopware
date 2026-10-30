/**
 * @sw-package framework
 */

import { getCurrentInstance, type ComponentInternalInstance, type Directive } from 'vue';

/**
 * Silences the deprecations of a major for the component it sits on and everything that component renders,
 * the template counterpart of `Feature.silence()`. Use it on the pre-major side of a `v-if` that keeps a deprecated
 * component or prop until the major. It does nothing on a `<template>`, which Vue renders as a fragment.
 *
 * The directive itself has no hooks: `Feature.isSilenced()` looks it up on the component vnodes above the running
 * component. Once the major is active nothing is silenced, so a use outside of its branch still throws.
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_DEPRECATION_SILENCE
 * @example
 * <sw-loader v-if="!feature.isActive('V6_8_0_0')" v-silence-flag="'V6_8_0_0'" />
 * <mt-loader v-else />
 */
const silenceFlag: Directive<HTMLElement, string> = {};

Shopware.Directive.register('silence-flag', silenceFlag);

/**
 * Whether a directive value names `majorFlag`, in either spelling.
 *
 * @example
 * sameFlag('v6.8.0.0', 'V6_8_0_0'); // true
 */
function sameFlag(value: unknown, majorFlag: string): boolean {
    return (
        typeof value === 'string' && value.toUpperCase().replace(/\./g, '_') === majorFlag.toUpperCase().replace(/\./g, '_')
    );
}

/**
 * Whether the running component, or a component above it, carries `v-silence-flag` for `majorFlag`.
 *
 * @example
 * isSilencedByTemplate('V6_8_0_0'); // true while `<sw-loader v-silence-flag="'V6_8_0_0'" />` renders
 */
function isSilencedByTemplate(majorFlag: string): boolean {
    if (Shopware.Feature.isActive(majorFlag)) {
        return false;
    }

    for (let instance: ComponentInternalInstance | null = getCurrentInstance(); instance; instance = instance.parent) {
        if (instance.vnode.dirs?.some(({ dir, value }) => dir === silenceFlag && sameFlag(value, majorFlag))) {
            return true;
        }
    }

    return false;
}

Shopware.Feature.addSilenceCheck(isSilencedByTemplate);

/**
 * @private
 */
export {};
