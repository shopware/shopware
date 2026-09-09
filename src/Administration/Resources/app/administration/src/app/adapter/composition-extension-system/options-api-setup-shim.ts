/**
 * @sw-package framework
 * @private
 *
 * Runs native setup overrides against components that are still rendered through the Twig pipeline.
 *
 * The whole module works around one ordering constraint: only a `setup()` return value outranks
 * `data` and `computed` in Vue's instance proxy, but `setup()` runs before either of them exists.
 * So the slot is reserved in `setup()` and filled in `created()`.
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */

import { getCurrentInstance, isRef } from 'vue';
import type { ComponentInternalInstance, SetupContext } from '@vue/runtime-core';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { _overridesMap } from './index';

type AnyRecord = Record<string, unknown>;
type SetupResult = AnyRecord | ((...args: unknown[]) => unknown) | undefined;

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

    const originalSetup = config.setup;

    // Vue keeps a live reference to whatever setup() returns as `setupState`, which is what later lets
    // created() write into it. The overrides cannot run here - `data` and `computed` do not exist yet,
    // so previousState would be empty. An existing setup() keeps its own return value as the starting
    // content instead of being replaced.
    config.setup = function shimSetup(props: Record<string, unknown>, context: SetupContext) {
        const originalResult = (originalSetup ? originalSetup.call(this, props, context) : undefined) as SetupResult;

        // A setup() returning a render function cannot carry the bag. Leave it untouched; the created
        // hook then finds no bag and bails out, so the component keeps working without the overrides.
        // Reported, because from the override author's side nothing else hints at why it has no effect.
        if (typeof originalResult === 'function') {
            console.warn(
                `[${componentName}] Setup overrides not applied: setup() returns a render function, which leaves no place for override results. Return an object from setup() to make the ${_overridesMap[componentName].length} registered override(s) take effect.`,
            );

            return originalResult;
        }

        const bag: AnyRecord = originalResult ?? {};
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

            // Deliberately skips `setupState`: what an earlier override or the component's own setup()
            // put there is served from the per-override snapshot below. Mirrors Vue's own order minus
            // that first step.
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

            // Override callbacks read `previousState.x.value`, so plain values are served as a ref-like
            // accessor. Refs pass through as they are; functions stay callable as `previousState.x()`,
            // matching what createExtendableSetup() hands to overrides of migrated components.
            // previousState is read-only: state changes go through the override's return value, so a
            // write attempt is reported and dropped instead of reaching data or ctx behind Vue's back.
            const toRefLike = (key: string, read: () => unknown): unknown => {
                const current = read();

                if (isRef(current) || typeof current === 'function') {
                    return current;
                }

                return {
                    __v_isRef: true,
                    get value() {
                        return read();
                    },
                    set value(_next: unknown) {
                        console.error(
                            `[${componentName}] previousState is read-only. Return "${key}" from the override instead of assigning to previousState.${key}.value.`,
                        );
                    },
                };
            };

            // Resolves `installed` first, then the base state - the same order Vue's instance proxy uses,
            // with `installed` standing in for setupState. A proxy avoids having to enumerate data,
            // computed and methods upfront.
            const createPreviousState = (installed: AnyRecord): AnyRecord =>
                new Proxy({} as AnyRecord, {
                    get: (_target, key) => {
                        // Vue probes objects with `__v_isRef`, `__v_raw` & co and with symbol keys.
                        // Answering those with an accessor would make the proxy itself look like a ref.
                        if (typeof key !== 'string' || key.startsWith('__v_')) {
                            return undefined;
                        }

                        if (Object.prototype.hasOwnProperty.call(installed, key)) {
                            return toRefLike(key, () => installed[key]);
                        }

                        return toRefLike(key, () => readBaseState(key));
                    },
                });

            const context = {
                attrs: instance.attrs,
                slots: instance.slots,
                emit: instance.emit,
                expose: () => {},
            } as SetupContext;

            // Vue activates the component's effect scope around lifecycle hooks, so watchers and
            // computeds the overrides create here are disposed on unmount without further handling.
            _overridesMap[componentName].forEach((override) => {
                // Snapshot per override: the bag already holds the component's own setup() result and
                // everything earlier overrides installed, so override N sees N-1 - but not its own
                // result, which is only written after the call. That keeps the read from looping.
                const previousState = createPreviousState({ ...bag });
                const result = override(previousState as never, instance.props as never, context) as AnyRecord;

                if (result === undefined) {
                    return;
                }

                Object.keys(result).forEach((key) => {
                    bag[key] = result[key];
                    // Vue memoises which bucket a key resolved from on first access. Anything that read the
                    // key earlier - an immediate watcher, a preceding created hook - pinned it to `data`,
                    // and setupState would never be consulted again.
                    delete (instance as unknown as { accessCache: Record<string, unknown> }).accessCache[key];
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
