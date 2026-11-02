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
 * Whether the running component sits in a `sw-silence-deprecations` for `majorFlag`, or is rendered by one that
 * does. A wrapper silences what its own template wrote inside of it, but not the slot content another template
 * passes through, as that content keeps its own template as `vnode.ctx`.
 *
 * @example
 * isSilencedByScope('V6_8_0_0'); // true while `sw-popover-deprecated` renders inside of the wrapper in `sw-popover`
 */
function isSilencedByScope(majorFlag: string): boolean {
    for (let current = getCurrentInstance(); current; current = authorOf(current)) {
        const author = authorOf(current);

        // a wrapper the same template wrote sits between the component and that template
        for (let ancestor = current.parent; ancestor && ancestor !== author; ancestor = ancestor.parent) {
            if (silencesMajor(ancestor, majorFlag) && authorOf(ancestor) === author) {
                return true;
            }
        }
    }

    return false;
}

Shopware.Feature.setSilenceCheck('sw-silence-deprecations', isSilencedByScope);

/**
 * Silences the deprecations of a major for what its template writes inside of it, the template counterpart of
 * `Feature.silence()`: the components there, the deprecated props passed to them, what they report while they are
 * created or rendered, and what their own templates render. Slot content that another template passes through, like
 * the `<slot />` of a wrapper component, still reports. Event handlers and watchers run outside of creating and
 * rendering, so they wrap their deprecated calls in `Feature.silence()`.
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
