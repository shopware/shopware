/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed, isRef, ref, watch } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { attachSetupOverrideShim } from './options-api-setup-shim';
import { _overridesMap } from './index';

// What the shim hands to an override: every base-state key served as a ref-like accessor.
type PreviousState = Record<string, { value: unknown }>;

describe('src/app/adapter/composition-extension-system/options-api-setup-shim', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('applies a setup override to an Options API component and reads the untouched base state', async () => {
        _overridesMap['sw-shim-test'] = [
            (previousState: PreviousState) => ({
                welcomeSubline: computed(() => `${String(previousState.welcomeSubline.value)} / overridden`),
                shopName: computed(() => `${String(previousState.shopName.value)} GmbH`),
            }),
        ] as never;

        const config = {
            template: '<p>{{ welcomeSubline }} — {{ shopName }}</p>',
            data() {
                return { shopName: 'Demo' };
            },
            computed: {
                welcomeSubline() {
                    return 'base subline';
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-test', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // Would read "undefined / overridden" if previousState resolved through the instance proxy,
        // because the override's own result already shadows the key at that point.
        expect(wrapper.text()).toBe('base subline / overridden — Demo GmbH');
    });

    it('rejects writes through previousState and keeps the base state untouched', async () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        let write = (): void => {};

        _overridesMap['sw-shim-write'] = [
            (previousState: PreviousState) => {
                write = () => {
                    previousState.counter.value = 42;
                };

                return { counter: computed(() => `override ${String(previousState.counter.value)}`) };
            },
        ] as never;

        const config = {
            template: '<p>{{ counter }}</p>',
            data() {
                return { counter: 1 };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-write', config);

        const wrapper = mount(config as never);
        await flushPromises();
        expect(wrapper.text()).toBe('override 1');

        write();
        await flushPromises();

        // State only changes through what an override returns; a write attempt is reported and dropped.
        expect(wrapper.text()).toBe('override 1');
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('previousState is read-only'));
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('"counter"'));

        errorSpy.mockRestore();
    });

    it('keeps an existing setup() of the component instead of replacing it', async () => {
        _overridesMap['sw-shim-existing-setup'] = [
            () => ({ fromOverride: computed(() => 'override') }),
        ] as never;

        const config = {
            template: '<p>{{ fromSetup }}|{{ fromOverride }}</p>',
            setup() {
                return { fromSetup: ref('own setup') };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-existing-setup', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // Would render only "|override" if the shim had overwritten config.setup.
        expect(wrapper.text()).toBe('own setup|override');
    });

    it('applies the override even when an immediate watcher read the key first', async () => {
        _overridesMap['sw-shim-watch'] = [
            (previousState: PreviousState) => ({
                label: computed(() => `override ${String(previousState.label.value)}`),
            }),
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
            watch: {
                label: {
                    immediate: true,
                    handler() {},
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-watch', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // The watcher fires before any created hook and pins the key to `data` in Vue's access cache.
        expect(wrapper.text()).toBe('override base');
    });

    it('reads, calls and replaces a method through previousState', async () => {
        let originalResult = '';

        _overridesMap['sw-shim-methods'] = [
            // Methods arrive raw, not as a ref - the same shape createExtendableSetup() hands out.
            (previousState: { greet: () => string }) => {
                const original = previousState.greet;
                originalResult = original();

                return { greet: () => `${original()} + override` };
            },
        ] as never;

        const config = {
            template: '<p>{{ greet() }}</p>',
            data() {
                return { name: 'world' };
            },
            methods: {
                greet(this: { name: string }) {
                    return `hello ${this.name}`;
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-methods', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // Methods live on `instance.ctx`, already bound by Vue - so the original stays callable from
        // inside the override, which is what makes a super-style call possible.
        expect(originalResult).toBe('hello world');
        expect(wrapper.text()).toBe('hello world + override');
    });

    it('lets a later override read what an earlier override installed', async () => {
        _overridesMap['sw-shim-chain'] = [
            (previousState: PreviousState) => ({
                label: computed(() => `${String(previousState.label.value)} +1`),
            }),
            (previousState: PreviousState) => ({
                label: computed(() => `${String(previousState.label.value)} +2`),
            }),
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-chain', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // Would read "base +2" if the second override resolved against the base state only.
        expect(wrapper.text()).toBe('base +1 +2');
    });

    it('exposes keys an earlier override introduced to later overrides', async () => {
        _overridesMap['sw-shim-new-key'] = [
            () => ({ extra: computed(() => 'from first') }),
            (previousState: PreviousState) => ({
                label: computed(() => `${String(previousState.extra.value)} / second`),
            }),
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-new-key', config);

        const wrapper = mount(config as never);
        await flushPromises();

        expect(wrapper.text()).toBe('from first / second');
    });

    it('does not let an earlier override see a later override', async () => {
        let seenByFirst: unknown = 'unset';

        _overridesMap['sw-shim-order'] = [
            (previousState: PreviousState) => {
                seenByFirst = previousState.label.value;

                return {};
            },
            () => ({ label: computed(() => 'second') }),
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-order', config);

        const wrapper = mount(config as never);
        await flushPromises();

        expect(seenByFirst).toBe('base');
        expect(wrapper.text()).toBe('second');
    });

    it("exposes the component's own setup() result through previousState", async () => {
        _overridesMap['sw-shim-setup-keys'] = [
            (previousState: PreviousState) => ({
                fromSetup: computed(() => `${String(previousState.fromSetup.value)} / overridden`),
            }),
        ] as never;

        const config = {
            template: '<p>{{ fromSetup }}</p>',
            setup() {
                return { fromSetup: ref('own setup') };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-setup-keys', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // setup() keys live in the bag, not in data/props/ctx - only the snapshot makes them visible.
        expect(wrapper.text()).toBe('own setup / overridden');
    });

    it('chains a method replaced by an earlier override into a later one', async () => {
        _overridesMap['sw-shim-method-chain'] = [
            (previousState: { greet: () => string }) => {
                const original = previousState.greet;

                return { greet: () => `${original()} + first` };
            },
            (previousState: { greet: () => string }) => {
                const previous = previousState.greet;

                return { greet: () => `${previous()} + second` };
            },
        ] as never;

        const config = {
            template: '<p>{{ greet() }}</p>',
            data() {
                return { name: 'world' };
            },
            methods: {
                greet(this: { name: string }) {
                    return `hello ${this.name}`;
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-method-chain', config);

        const wrapper = mount(config as never);
        await flushPromises();

        expect(wrapper.text()).toBe('hello world + first + second');
    });

    it('does not present previousState itself as a ref', async () => {
        let looksLikeRef: boolean | undefined;

        _overridesMap['sw-shim-not-a-ref'] = [
            (previousState: PreviousState) => {
                looksLikeRef = isRef(previousState);

                return {};
            },
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-not-a-ref', config);

        mount(config as never);
        await flushPromises();

        expect(looksLikeRef).toBe(false);
    });

    it('disposes watchers an override creates when the component unmounts', async () => {
        const source = ref(0);
        const handler = jest.fn();

        _overridesMap['sw-shim-scope'] = [
            () => {
                watch(source, handler);

                return { label: computed(() => 'x') };
            },
        ] as never;

        const config = {
            template: '<p>{{ label }}</p>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-scope', config);

        const wrapper = mount(config as never);
        await flushPromises();

        source.value += 1;
        await flushPromises();
        expect(handler).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        source.value += 1;
        await flushPromises();

        // Vue activates the instance scope around lifecycle hooks; this pins that outcome, so a change
        // there surfaces here instead of as a leak in production.
        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('keeps Vue resolving late-added setup keys before data and computed', async () => {
        const bag: Record<string, unknown> = {};

        const wrapper = mount({
            template: '<p>{{ fromData }}|{{ fromComputed }}</p>',
            setup() {
                return bag;
            },
            data() {
                return { fromData: 'DATA' };
            },
            computed: {
                fromComputed() {
                    return 'COMPUTED';
                },
            },
            created() {
                bag.fromData = 'SETUP';
                bag.fromComputed = 'SETUP';
            },
        });
        await flushPromises();

        // The shim rests on this undocumented Vue behaviour. If a Vue upgrade changes it, this fails
        // here instead of silently dropping every override at runtime.
        expect(wrapper.text()).toBe('SETUP|SETUP');
    });
});
