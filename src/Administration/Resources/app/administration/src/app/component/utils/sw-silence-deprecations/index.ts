/**
 * @sw-package framework
 */
import { getCurrentInstance, type ComponentInternalInstance } from 'vue';
import { silenceKey } from 'src/core/feature';
import { error } from 'src/core/service/utils/debug.utils';

/** The rendered `sw-silence-deprecations`, found by instance, as overrides and test wrappers change its name. */
const scopes = new WeakSet<ComponentInternalInstance>();

/**
 * Whether `instance` is a `sw-silence-deprecations` for `majorFlag`, spelled as `silenceKey()` spells it.
 */
function silencesMajor(instance: ComponentInternalInstance, majorFlag: string): boolean {
    const flag: unknown = instance.props.flag;

    return scopes.has(instance) && typeof flag === 'string' && silenceKey(flag) === majorFlag;
}

/**
 * The instance whose template created the vnode of `instance`, kept by Vue as the internal `vnode.ctx`.
 */
function authorOf(instance: ComponentInternalInstance): ComponentInternalInstance | null {
    return (instance.vnode as { ctx?: ComponentInternalInstance | null }).ctx ?? null;
}

/**
 * Whether the running component is on the first level of a `sw-silence-deprecations` for `majorFlag` in the same
 * template, or is rendered by a component that is. Slot content keeps reporting, as its parent is the component it is
 * passed into, not the wrapper.
 *
 * @example
 * isSilencedByScope('V6_8_0_0'); // true while `sw-popover-deprecated` renders inside of the wrapper in `sw-popover`
 */
function isSilencedByScope(majorFlag: string): boolean {
    for (let current = getCurrentInstance(); current; current = authorOf(current)) {
        const parent = current.parent;

        if (parent && silencesMajor(parent, majorFlag) && authorOf(parent) === authorOf(current)) {
            return true;
        }
    }

    return false;
}

Shopware.Feature.setSilenceCheck('sw-silence-deprecations', isSilencedByScope);

/**
 * Silences the deprecations of a major for the first level of components inside of it, the template counterpart of
 * `Feature.silence()`, ignoring plain HTML elements and `<template>`. Those components are silenced with the
 * deprecated props passed to them and everything they render themselves. Everything below them in the same template
 * reports, like the tab content of a `sw-tabs` or the `<slot />` of a wrapper component.
 *
 * It covers what runs while Vue knows the running component: `setup`, `data`, lifecycle hooks and rendering. Event
 * handlers, watchers that are not `immediate`, and code after an `await` wrap their deprecated calls in
 * `Feature.silence()`.
 *
 * Use it on the pre-major side of a `v-if` that keeps a deprecated component or prop until the major. Once the major
 * is active nothing is silenced, so a use outside of its branch still throws. An unknown flag silences nothing and
 * logs an error.
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_DEPRECATION_SILENCE
 * @example
 * <sw-silence-deprecations v-if="!feature.isActive('V6_8_0_0')" flag="V6_8_0_0">
 *     <sw-popover-deprecated v-bind="$attrs"><slot /></sw-popover-deprecated>
 * </sw-silence-deprecations>
 * <mt-floating-ui v-else v-bind="$attrs"><slot /></mt-floating-ui>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- extensions use it in their templates
export default Shopware.Component.wrapComponentConfig({
    template: '<slot />',

    inheritAttrs: false,

    props: {
        flag: {
            type: String,
            required: true,
        },
    },

    created() {
        scopes.add(this.$);

        if (!Shopware.Feature.isKnownMajor(silenceKey(this.flag))) {
            // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- reports a typo in the flag, not a deprecation
            error(
                'Feature',
                `<sw-silence-deprecations flag="${this.flag}"> silences nothing: "${this.flag}" is an unknown feature flag. Please fix it.`,
            );
        }
    },
});
