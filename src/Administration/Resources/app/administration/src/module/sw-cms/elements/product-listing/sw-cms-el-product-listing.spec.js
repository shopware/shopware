/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';

import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/state/cms-page.state';

import swCmsElProductListing from 'src/module/sw-cms/elements/product-listing/component/index';

Shopware.Component.register('sw-cms-el-product-listing', swCmsElProductListing);

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
    return shallowMount(await Shopware.Component.build('sw-cms-el-product-listing'), {
        data() {
            return {
                cmsPageState: {
                    currentDemoProducts,
                },
            };
        },
        propsData: {
            element: {
                config: {
                    boxLayout: {
                        value: 'standard',
                    },
                },
            },
        },
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
    });
}


describe('module/sw-cms/elements/product-listing/component/index', () => {
    const cmsPageStateBackup = { ...Shopware.State._store.state.cmsPageState };

    beforeEach(async () => {
        Shopware.State._store.state.cmsPageState = { ...cmsPageStateBackup };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should use demo products', async () => {
        const wrapper = await createWrapper();

        Shopware.State.commit('cmsPageState/setCurrentDemoProducts', currentDemoProducts);

        await wrapper.vm.$nextTick();

        const productBoxes = wrapper.findAllComponents({ name: 'sw-cms-el-product-box' });

        expect(productBoxes).toHaveLength(8);

        productBoxes.wrappers.forEach((productBox, index) => {
            const expectedDefaultConfig = { ...defaultConfig };

            const product = currentDemoProducts[index];
            if (product) {
                expectedDefaultConfig.data = { product };
            }

            expect(productBox.props('element')).toMatchObject(expectedDefaultConfig);
        });
    });
});
