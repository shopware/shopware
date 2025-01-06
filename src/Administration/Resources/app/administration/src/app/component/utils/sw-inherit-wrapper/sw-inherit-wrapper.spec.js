/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(options = {}) {
    return mount(await wrapTestComponent('sw-inherit-wrapper', { sync: true }), {
        ...options,
    });
}

const createWrapperGlobalValue = {
    stubs: {
        'sw-inheritance-switch': true,
        'sw-help-text': true,
    },
};

describe('src/app/component/utils/sw-inherit-wrapper', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not inherit on different values', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should inherit on same values', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: null,
                inheritedValue: 1,
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should have error classes', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
                error: {
                    detail: 'Whoops',
                },
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.labelClasses).toStrictEqual({
            'has--error': true,
        });
    });

    it('should inherit on empty array', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: [],
                inheritedValue: 1,
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.isInherited).toBe(true);
    });
});
