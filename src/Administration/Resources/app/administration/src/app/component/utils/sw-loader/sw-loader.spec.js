import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-loader';

function createWrapper(options = {}) {
    return shallowMount(Shopware.Component.build('sw-loader'), {
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

    it('should fallback to default values for size smaller than 12', async () => {
        wrapper = await createWrapper({
            propsData: {
                size: '11px',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('50px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('50px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('4px');
    });

    it('should fallback to default values for none numeric values', async () => {
        wrapper = await createWrapper({
            propsData: {
                size: 'zwölfpx',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('50px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('50px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('4px');
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

    it('should accept valid size without px suffix', async () => {
        wrapper = await createWrapper({
            propsData: {
                size: '24',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('24px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('24px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('2px');
    });
});
