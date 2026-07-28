/**
 * @sw-package framework
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

    it('should not re-inherit after the user clears a previously detached field with a truthy parent value', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: null,
                inheritedValue: 'parent-id',
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm.isInherited).toBe(true);

        wrapper.vm.removeInheritance();
        await wrapper.setProps({ value: 'parent-id' });
        expect(wrapper.vm.isInherited).toBe(false);

        wrapper.vm.updateCurrentValue(null);
        await wrapper.setProps({ value: null });
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should not re-inherit after the user clears a field that already held its own value', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: 'own-id',
                inheritedValue: 'parent-id',
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm.isInherited).toBe(false);

        wrapper.vm.updateCurrentValue(null);
        await wrapper.setProps({ value: null });
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should not re-inherit a multi-select after removing the last value once it was detached', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: [],
                inheritedValue: ['parent-id'],
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        // starts inherited: own value is empty and the parent holds a value
        expect(wrapper.vm.isInherited).toBe(true);

        // user clicks unlink -> the inherited value is copied down
        wrapper.vm.removeInheritance();
        await wrapper.setProps({ value: ['parent-id'] });
        expect(wrapper.vm.isInherited).toBe(false);

        // user removes the last value -> the field must stay empty and detached
        await wrapper.setProps({ value: [] });
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should keep a multi-select detached while removing its values one by one', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: [],
                inheritedValue: [
                    'parent-a',
                    'parent-b',
                ],
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        expect(wrapper.vm.isInherited).toBe(true);

        // unlink from the parent
        wrapper.vm.removeInheritance();
        await wrapper.setProps({
            value: [
                'parent-a',
                'parent-b',
            ],
        });
        expect(wrapper.vm.isInherited).toBe(false);

        // remove the values one by one, ending with an empty selection
        wrapper.vm.updateCurrentValue(['parent-a']);
        await wrapper.setProps({ value: ['parent-a'] });
        expect(wrapper.vm.isInherited).toBe(false);

        wrapper.vm.updateCurrentValue([]);
        await wrapper.setProps({ value: [] });
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should re-inherit again after restoreInheritance is called', async () => {
        const wrapper = await createWrapper({
            propsData: {
                value: null,
                inheritedValue: 'parent-id',
                hasParent: true,
            },
            global: createWrapperGlobalValue,
        });

        wrapper.vm.removeInheritance();
        await wrapper.setProps({ value: 'parent-id' });
        wrapper.vm.updateCurrentValue(null);
        await wrapper.setProps({ value: null });
        expect(wrapper.vm.isInherited).toBe(false);

        wrapper.vm.restoreInheritance();
        expect(wrapper.vm.isInherited).toBe(true);
    });
});
