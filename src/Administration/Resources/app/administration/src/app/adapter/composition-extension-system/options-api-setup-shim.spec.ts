/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed } from 'vue';
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
