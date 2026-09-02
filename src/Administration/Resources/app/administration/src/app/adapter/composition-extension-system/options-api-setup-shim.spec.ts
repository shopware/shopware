/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed, ref } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { attachSetupOverrideShim } from './options-api-setup-shim';
import { _overridesMap } from './index';

describe('src/app/adapter/composition-extension-system/options-api-setup-shim', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('applies a setup override to an Options API component and reads the untouched base state', async () => {
        _overridesMap['sw-shim-test'] = [
            (previousState: Record<string, { value: unknown }>) => ({
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

    it('writes through previousState to the base state, not to the override result', async () => {
        let write = (): void => {};

        _overridesMap['sw-shim-write'] = [
            (previousState: Record<string, { value: unknown }>) => {
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

        // Would stay at "override 1" if the setter wrote into the override's own result instead of data.
        expect(wrapper.text()).toBe('override 42');
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
