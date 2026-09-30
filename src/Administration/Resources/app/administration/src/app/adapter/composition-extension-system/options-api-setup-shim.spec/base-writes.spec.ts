/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed, ref } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { attachSetupOverrideShim } from '../options-api-setup-shim';
import { _overridesMap } from '../index';

// What the shim hands to an override: every base-state key served as a ref-like accessor.
type PreviousState = Record<string, { value: unknown }>;

type BaseVm = { isLoading: boolean; startLoading: () => void; $data: { isLoading: boolean } };

// Writes a data key through `this`, the way every Options API component does, e.g. in getList().
function createConfig(): ComponentConfig {
    return {
        template: '<p>{{ isLoading }}</p>',
        data() {
            return { isLoading: false };
        },
        methods: {
            startLoading(this: BaseVm) {
                this.isLoading = true;
            },
        },
    } as unknown as ComponentConfig;
}

describe('src/app/adapter/composition-extension-system/options-api-setup-shim - base writes', () => {
    let warnSpy: jest.SpyInstance;
    let errorSpy: jest.SpyInstance;

    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });

        warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
        errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        warnSpy.mockRestore();
        errorSpy.mockRestore();
    });

    it('lets the base write a data key an override derives from previousState', async () => {
        _overridesMap['sw-shim-base-write-computed'] = [
            (previousState: PreviousState) => ({
                isLoading: computed(() => previousState.isLoading.value),
            }),
        ] as never;

        const config = createConfig();
        attachSetupOverrideShim('sw-shim-base-write-computed', config);

        const wrapper = mount(config as never);
        await flushPromises();
        expect(wrapper.text()).toBe('false');

        (wrapper.vm as unknown as BaseVm).startLoading();
        await flushPromises();

        // Without routing, the write would hit the read-only computed in setupState: Vue warns, data
        // stays false and the override derived from it never changes.
        expect(wrapper.text()).toBe('true');
        expect((wrapper.vm as unknown as BaseVm).$data.isLoading).toBe(true);
        expect(warnSpy).not.toHaveBeenCalled();
    });

    it('lets the base write a data key an override passes through from previousState', async () => {
        _overridesMap['sw-shim-base-write-pass-through'] = [
            (previousState: PreviousState) => ({ isLoading: previousState.isLoading }),
        ] as never;

        const config = createConfig();
        attachSetupOverrideShim('sw-shim-base-write-pass-through', config);

        const wrapper = mount(config as never);
        await flushPromises();

        (wrapper.vm as unknown as BaseVm).startLoading();
        await flushPromises();

        // The previousState wrapper reports writes instead of rejecting them, so isReadonly() alone
        // would let this one through and blame the override for a write it never made.
        expect(wrapper.text()).toBe('true');
        expect(errorSpy).not.toHaveBeenCalled();
    });

    it('keeps a plain value override when the base writes the key', async () => {
        _overridesMap['sw-shim-base-write-plain'] = [
            () => ({ isLoading: 'always' }),
        ] as never;

        const config = createConfig();
        attachSetupOverrideShim('sw-shim-base-write-plain', config);

        const wrapper = mount(config as never);
        await flushPromises();

        (wrapper.vm as unknown as BaseVm).startLoading();
        await flushPromises();

        // Would be replaced by `true` in setupState if the write were not sent back to data.
        expect(wrapper.text()).toBe('always');
        expect((wrapper.vm as unknown as BaseVm).$data.isLoading).toBe(true);
    });

    it('sends base writes to a writable ref the override owns', async () => {
        const ownState = ref(false);

        _overridesMap['sw-shim-base-write-own-ref'] = [
            () => ({ isLoading: ownState }),
        ] as never;

        const config = createConfig();
        attachSetupOverrideShim('sw-shim-base-write-own-ref', config);

        const wrapper = mount(config as never);
        await flushPromises();

        (wrapper.vm as unknown as BaseVm).startLoading();
        await flushPromises();

        // The override replaced the state with its own ref, so writes from the base or from a v-model
        // in the override template have to reach that ref.
        expect(ownState.value).toBe(true);
        expect(wrapper.text()).toBe('true');
        expect((wrapper.vm as unknown as BaseVm).$data.isLoading).toBe(false);
    });

    describe('writable computed', () => {
        type FieldVm = { onInput: (value: string) => void };

        // The v-model pattern of most form fields: the computed setter emits instead of storing.
        function createFieldConfig(): ComponentConfig {
            return {
                template: '<p>{{ currentValue }}</p>',
                props: {
                    value: { type: String, default: 'base' },
                },
                emits: ['update:value'],
                computed: {
                    currentValue: {
                        get(this: { value: string }) {
                            return this.value;
                        },
                        set(this: { $emit: (event: string, value: unknown) => void }, value: unknown) {
                            this.$emit('update:value', value);
                        },
                    },
                },
                methods: {
                    onInput(this: { currentValue: unknown }, value: string) {
                        this.currentValue = value;
                    },
                },
            } as unknown as ComponentConfig;
        }

        it('lets the base write a computed setter an override derives from previousState', async () => {
            _overridesMap['sw-shim-base-write-computed-setter'] = [
                (previousState: PreviousState) => ({
                    currentValue: computed(() => `overridden ${String(previousState.currentValue.value)}`),
                }),
            ] as never;

            const config = createFieldConfig();
            attachSetupOverrideShim('sw-shim-base-write-computed-setter', config);

            const wrapper = mount(
                config as never,
                {
                    props: {
                        value: 'base',
                        'onUpdate:value': async (value: string) => {
                            await wrapper.setProps({ value } as never);
                        },
                    },
                } as never,
            );
            await flushPromises();
            expect(wrapper.text()).toBe('overridden base');

            (wrapper.vm as unknown as FieldVm).onInput('typed');
            await flushPromises();

            // Without routing, the write would hit the read-only computed in setupState: Vue warns, the base
            // setter never emits and a v-model on the component stops working.
            expect(wrapper.emitted('update:value')).toEqual([['typed']]);
            expect(wrapper.text()).toBe('overridden typed');
            expect(warnSpy).not.toHaveBeenCalled();
            expect(errorSpy).not.toHaveBeenCalled();
        });

        it('sends base writes to a writable computed the override owns instead of the computed setter', async () => {
            const ownSetter = jest.fn();

            _overridesMap['sw-shim-base-write-computed-setter-own-computed'] = [
                (previousState: PreviousState) => ({
                    currentValue: computed({
                        get: () => `overridden ${String(previousState.currentValue.value)}`,
                        set: ownSetter,
                    }),
                }),
            ] as never;

            const config = createFieldConfig();
            attachSetupOverrideShim('sw-shim-base-write-computed-setter-own-computed', config);

            const wrapper = mount(config as never);
            await flushPromises();

            (wrapper.vm as unknown as FieldVm).onInput('typed');
            await flushPromises();

            // A setter of its own means the override takes over writes, so the base setter must not emit.
            expect(ownSetter).toHaveBeenCalledWith('typed');
            expect(wrapper.emitted('update:value')).toBeUndefined();
            expect(wrapper.text()).toBe('overridden base');
        });
    });
});
