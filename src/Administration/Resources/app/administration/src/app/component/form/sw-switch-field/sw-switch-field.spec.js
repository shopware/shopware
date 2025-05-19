/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-switch-field', { sync: true }), {
        global: {
            stubs: {
                'sw-switch-field-deprecated': true,
                'mt-switch': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-switch-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the deprecated switch-field when major feature flag is disabled', async () => {
        global.activeFeatureFlags = [''];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('sw-switch-field-deprecated');
        expect(wrapper.html()).not.toContain('mt-switch');
    });

    it('should render the mt-switch when major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-switch');
    });

    it('should use the correct checked value', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

        const wrapper = await createWrapper();
        expect(wrapper.vm.checkedValue).toBe(false);

        await wrapper.setProps({ value: true });
        expect(wrapper.vm.checkedValue).toBe(true);

        await wrapper.setProps({ checked: true, value: null });
        expect(wrapper.vm.checkedValue).toBe(true);
    });

    it('should filter out update:value event listener', async () => {
        const baseComponent = {
            template: `
            <div>
                <sw-switch-field @update:value="test" @test-event="test"></sw-switch-field>
            </div>,            
        `,
            methods: {
                test() {}
            }
        };

        const switchField = await wrapTestComponent('sw-switch-field', { sync: true });
        const wrapper = mount(baseComponent, {
            global: {
                stubs: {
                    'sw-switch-field': switchField,
                    'sw-switch-field-deprecated': true,
                    'mt-switch': true,
                },
            }
        });

        const listeners = wrapper.findComponent(switchField).vm.listeners;

        if (!wrapper.findComponent(switchField).vm.isCompatEnabled('INSTANCE_LISTENERS')) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(listeners).toEqual({});
        } else {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(Object.keys(listeners)).toEqual(['testEvent']);
        }
    });
});
