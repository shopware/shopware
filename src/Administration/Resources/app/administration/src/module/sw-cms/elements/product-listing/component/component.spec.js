/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const currentDemoProducts = [
    { id: 'PRODUCT-0' },
    { id: 'PRODUCT-1' },
    { id: 'PRODUCT-2' },
    { id: 'PRODUCT-3' },
];

const defaultConfig = {
    config: {
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
    },
    data: null,
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-product-listing', {
        sync: true,
    }), {
        props: {
            element: {
                config: {
                    boxLayout: {
                        value: 'standard',
                    },
                },
            },
        },
        global: {
            stubs: {
                'sw-cms-el-product-box': {
                    name: 'sw-cms-el-product-box',
                    template: '<div>Product-Box</div>',
                    props: ['element'],
                },
                'sw-icon': true,
            },
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'product-listing': {} };
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/elements/product-listing/component/index', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/product-listing');
    });

    beforeEach(async () => {
        Shopware.Store.get('cmsPageState').resetCmsPageState();
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use demo products', async () => {
        const wrapper = await createWrapper();
        Shopware.Store.get('cmsPageState').setCurrentDemoProducts(currentDemoProducts);

        await wrapper.vm.$nextTick();
        const productBoxes = wrapper.findAllComponents({ name: 'sw-cms-el-product-box' });

        expect(productBoxes).toHaveLength(currentDemoProducts.length);
        productBoxes.forEach((productBox, index) => {
            const product = currentDemoProducts[index];

            expect(productBox.props('element')).toMatchObject({ ...defaultConfig, data: { product } });
        });
    });

    it('should use fallback to empty products', async () => {
        const wrapper = await createWrapper();
        Shopware.Store.get('cmsPageState').setCurrentDemoProducts([]);

        await wrapper.vm.$nextTick();
        const productBoxes = wrapper.findAllComponents({ name: 'sw-cms-el-product-box' });

        expect(productBoxes).toHaveLength(8);
        productBoxes.forEach((productBox) => {
            expect(productBox.props('element')).toMatchObject({ ...defaultConfig, data: null });
        });
    });
});
