/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-product-slider', { sync: true }), {
        props: {
            element: {
                type: 'product-slider',
            },
        },
        global: {
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
            stubs: {
                'sw-cms-el-product-box': await wrapTestComponent('sw-cms-el-product-box'),
            },
        },
    });
}

describe('src/module/sw-cms/elements/product-slider/component', () => {
    let resizeCallback = null;
    let observeMock = null;
    let disconnectMock = null;

    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-slider');
    });

    beforeEach(() => {
        resizeCallback = null;
        observeMock = jest.fn();
        disconnectMock = jest.fn();

        window.ResizeObserver = jest.fn().mockImplementation((callback) => {
            resizeCallback = callback;

            return {
                observe: observeMock,
                disconnect: disconnectMock,
            };
        });
    });

    it('mounts the component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeDefined();
    });

    it('observes the product holder to recalculate the box limit', async () => {
        const wrapper = await createWrapper();

        expect(observeMock).toHaveBeenCalledWith(wrapper.vm.$refs.productHolder);
    });

    it('recalculates the box limit when the product holder resizes but never shows more than three', async () => {
        const wrapper = await createWrapper();

        jest.spyOn(wrapper.vm.$refs.productHolder, 'offsetWidth', 'get').mockReturnValue(1360);
        wrapper.vm.element.config.elMinWidth.value = '300px';

        resizeCallback();

        expect(wrapper.vm.sliderBoxLimit).toBe(3);
    });

    it('disconnects the resize observer before unmount', async () => {
        const wrapper = await createWrapper();

        wrapper.unmount();

        expect(disconnectMock).toHaveBeenCalled();
    });
});
