/**
 * @sw-package framework
 * @private
 *
 * Runs native setup overrides against components that are still rendered through the Twig pipeline.
 *
 * The whole module works around one ordering constraint: only a `setup()` return value outranks
 * `data` and `computed` in Vue's instance proxy, but `setup()` runs before either of them exists.
 * So the slot is reserved in `setup()` and filled in `created()`.
 */

import { getCurrentInstance } from 'vue';
import type { ComponentInternalInstance, SetupContext } from '@vue/runtime-core';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { _overridesMap } from './index';

type AnyRecord = Record<string, unknown>;

// Hands the object created in setup() over to created(). Keyed per instance because the config is
// shared by every instance of the component.
const bags = new WeakMap<ComponentInternalInstance, AnyRecord>();

/**
 * @private
 */
export function attachSetupOverrideShim(componentName: string, config: ComponentConfig): void {
    // A string template means the component came out of the Twig pipeline; migrated SFCs run their
    // overrides through createExtendableSetup() instead.
    if (typeof config.template !== 'string' || !_overridesMap[componentName]?.length) {
        return;
    }

    // Returns an empty object on purpose. Vue keeps a live reference to it as `setupState`, which is
    // what later lets created() write into it. The overrides cannot run here - `data` and `computed`
    // do not exist yet, so previousState would be empty.
    config.setup = () => {
        const bag: AnyRecord = {};
        const instance = getCurrentInstance();

        if (instance) {
            bags.set(instance, bag);
        }

        return bag;
    };

    const shimMixin = {
        created(this: { $: ComponentInternalInstance }) {
            const instance = this.$;
            const bag = bags.get(instance);

            if (!bag) {
                return;
            }

            const proxy = instance.proxy as unknown as AnyRecord;

            // Deliberately skips `setupState`: reading through the instance proxy would resolve to the
            // override's own result and loop. Mirrors Vue's own order minus that first step.
            const readBaseState = (key: string): unknown => {
                const data = instance.data as AnyRecord;

                if (data && key in data) {
                    return data[key];
                }

                if (instance.props && key in instance.props) {
                    return (instance.props as AnyRecord)[key];
                }

                return (instance as unknown as { ctx: AnyRecord }).ctx[key];
            };

            // Override callbacks read `previousState.x.value`, so every key is served as a ref-like
            // accessor. A proxy avoids having to enumerate data, computed and methods upfront.
            const previousState = new Proxy(
                {},
                {
                    get: (_target, key: string) => ({
                        __v_isRef: true,
                        get value() {
                            return readBaseState(key);
                        },
                        set value(next: unknown) {
                            proxy[key] = next;
                        },
                    }),
                },
            );

            const context = {
                attrs: instance.attrs,
                slots: instance.slots,
                emit: instance.emit,
                expose: () => {},
            } as SetupContext;

            // Re-enters the component scope so watchers and computeds the overrides create are disposed
            // on unmount - `created()` has no active scope of its own.
            (instance as unknown as { scope: { run: (fn: () => void) => void } }).scope.run(() => {
                _overridesMap[componentName].forEach((override) => {
                    const result = override(previousState as never, instance.props as never, context) as AnyRecord;

                    if (result === undefined) {
                        return;
                    }

                    Object.keys(result).forEach((key) => {
                        bag[key] = result[key];
                    });
                });
            });
        },
    };

    const existingMixins = (config.mixins ?? []) as unknown[];

    // Placed first: Vue caches the bucket a key resolves to on first access, so any created() hook that
    // touches an overridden key before this one would pin it to `data` and the override would be lost.
    config.mixins = [
        shimMixin,
        ...existingMixins,
    ] as ComponentConfig['mixins'];
}
