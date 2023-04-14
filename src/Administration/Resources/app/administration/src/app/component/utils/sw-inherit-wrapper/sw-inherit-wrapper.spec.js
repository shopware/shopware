/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-inherit-wrapper';

async function createWrapper(options = {}) {
    return shallowMount(await Shopware.Component.build('sw-inherit-wrapper'), {
        ...options,
    });
}

describe('src/app/component/utils/sw-inherit-wrapper', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not inherit on different values', async () => {
        wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
                hasParent: true,
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('should inherit on same values', async () => {
        wrapper = await createWrapper({
            propsData: {
                value: null,
                inheritedValue: 1,
                hasParent: true,
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should have error classes', async () => {
        wrapper = await createWrapper({
            propsData: {
                value: 1,
                inheritedValue: 2,
                error: {
                    detail: 'Whoops',
                },
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.vm.labelClasses).toStrictEqual({
            'has--error': true,
        });
    });
});
