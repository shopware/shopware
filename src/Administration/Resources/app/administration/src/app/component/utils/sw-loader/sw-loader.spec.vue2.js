/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-loader';

async function createWrapper(options = {}) {
    return shallowMount(await Shopware.Component.build('sw-loader'), {
        ...options,
    });
}

describe('sr/app/component/utils/sw-loader', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }
    });

    it('should be a Vue.JS component with default values', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('50px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('50px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('4px');
    });

    it('should throw error for size smaller than 12px', async () => {
        const errorSpy = jest.fn();
        jest.spyOn(global.console, 'error').mockImplementation(errorSpy);
        wrapper = await createWrapper({
            propsData: {
                size: '11px',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(errorSpy).toHaveBeenCalledTimes(1);
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('Invalid prop: custom validator check failed for prop "size".'));
    });

    it('should throw error for none numeric values', async () => {
        const errorSpy = jest.fn();
        jest.spyOn(global.console, 'error').mockImplementation(errorSpy);
        wrapper = await createWrapper({
            propsData: {
                size: 'zwölfpx',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(errorSpy).toHaveBeenCalledTimes(1);
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('Invalid prop: custom validator check failed for prop "size".'));
    });

    it('should accept valid size with px suffix', async () => {
        wrapper = await createWrapper({
            propsData: {
                size: '20px',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('20px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('20px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('1px');
    });

    it('should throw error for size without px suffix', async () => {
        const errorSpy = jest.fn();
        jest.spyOn(global.console, 'error').mockImplementation(errorSpy);
        wrapper = await createWrapper({
            propsData: {
                size: '24',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(errorSpy).toHaveBeenCalledTimes(1);
        expect(errorSpy).toHaveBeenCalledWith(expect.stringContaining('Invalid prop: custom validator check failed for prop "size".'));
    });
});
