/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(options = {}) {
    return mount(await wrapTestComponent('sw-loader-deprecated', { sync: true }), {
        ...options,
    });
}

describe('sr/app/component/utils/sw-loader-deprecated', () => {
    it('should be a Vue.JS component with default values', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
        expect(typeof wrapper.vm.loaderSize).toBe('object');
        expect(wrapper.vm.loaderSize.hasOwnProperty('height')).toBe(true);
        expect(wrapper.vm.loaderSize.height).toBe('50px');
        expect(wrapper.vm.loaderSize.hasOwnProperty('width')).toBe(true);
        expect(wrapper.vm.loaderSize.width).toBe('50px');
        expect(typeof wrapper.vm.borderWidth).toBe('string');
        expect(wrapper.vm.borderWidth).toBe('4px');
    });

    it('should throw warning for size smaller than 12px', async () => {
        let showedWarning = false;
        const warnSpy = jest.fn((args) => {
            if (typeof args === 'string' && args.includes('Invalid prop: custom validator check failed for prop "size".')) {
                showedWarning = true;
            }
        });
        jest.spyOn(global.console, 'warn').mockImplementation(warnSpy);
        const wrapper = await createWrapper({
            props: {
                size: '11px',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(showedWarning).toBe(true);
    });

    it('should throw warning for none numeric values', async () => {
        let showedWarning = false;
        const warnSpy = jest.fn((args) => {
            if (typeof args === 'string' && args.includes('Invalid prop: custom validator check failed for prop "size".')) {
                showedWarning = true;
            }
        });
        jest.spyOn(global.console, 'warn').mockImplementation(warnSpy);
        const wrapper = await createWrapper({
            props: {
                size: 'zwölfpx',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(showedWarning).toBe(true);
    });

    it('should accept valid size with px suffix', async () => {
        const wrapper = await createWrapper({
            props: {
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

    it('should throw warning for size without px suffix', async () => {
        let showedWarning = false;
        const warnSpy = jest.fn((args) => {
            if (typeof args === 'string' && args.includes('Invalid prop: custom validator check failed for prop "size".')) {
                showedWarning = true;
            }
        });
        jest.spyOn(global.console, 'warn').mockImplementation(warnSpy);
        const wrapper = await createWrapper({
            props: {
                size: '24',
            },
        });

        expect(wrapper.vm).toBeTruthy();
        expect(showedWarning).toBe(true);
    });
});
