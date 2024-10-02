/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const productMock = [{
    id: 'de8de156da134dabac24257f81ff282f',
    name: 'Translated',
    translated: {
        name: 'Übersetzt',
    },
}, {
    id: 'c336e6ad6a174c76bb201ce7ba0e2ab3',
    name: 'Test',
    translated: {},
}];


const productStreamMock = {
    name: 'Cheap pc parts',
    apiFilter: ['foo', 'bar'],
    invalid: false,
};


async function createWrapper(customCmsElementConfig) {
    return mount(await wrapTestComponent('sw-cms-el-config-product-slider', {
        sync: true,
    }), {
        props: {
            element: {
                config: {
                    title: {
                        value: '',
                    },
                    products: {
                        value: ['de8de156da134dabac24257f81ff282f', '2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
                        source: 'static',
                    },
                    productStreamSorting: {
                        value: 'name:ASC',
                    },
                    productStreamLimit: {
                        value: 10,
                    },
                    ...customCmsElementConfig,
                },
            },
            defaultConfig: {},
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-single-select': true,
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': true,
                'sw-product-variant-info': true,
                'sw-label': true,
                'sw-modal': true,
                'sw-block-field': true,
                'sw-product-stream-modal-preview': true,
                'sw-entity-single-select': true,
                'sw-alert': true,
                'sw-number-field': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-popover': true,
                'sw-select-field': true,
                'sw-switch-field': true,
                'sw-highlight-text': true,
            },
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return {};
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(productStreamMock),
                            search: (criteria) => {
                                const products = criteria.ids.length ? productMock.slice(0, 1) : productMock;

                                products.has = id => products.some(i => i.id === id);

                                return Promise.resolve(products);
                            },
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/elements/product-slider/config', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPage',
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render product assignment type select', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-product-slider__product-assignment-type-select')
            .exists()).toBeTruthy();
    });

    it('should render manual product assignment by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-product-slider__products').exists()).toBeTruthy();
    });

    it('should fetch product stream when assignment type is "product_stream"', async () => {
        const wrapper = await createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream',
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStream).toEqual(productStreamMock);
    });

    it('should fetch product stream when changing product stream via select', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeProductStream('de8de156da134dabac24257f81ff282f');

        await wrapper.vm.$nextTick();


        expect(wrapper.vm.productStream).toEqual(productStreamMock);
    });

    it('should set product stream to null when changing product stream via select and no stream is given', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeProductStream(null);

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStream).toBeNull();
    });

    it('should render product stream selection when element product type is "product_stream"', async () => {
        const wrapper = await createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream',
            },
        });

        await wrapper.vm.$nextTick();

        // Product stream select should exist
        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-select').exists()).toBeTruthy();

        // Performance hint should exist
        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-performance-hint')
            .exists()).toBeTruthy();

        // Sorting fields should exist
        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-sorting').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-limit').exists()).toBeTruthy();
    });

    it('should store the productIds after changing the assignment type to "product_stream"', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeAssignmentType('product_stream');

        await wrapper.vm.$nextTick();

        const expectedProductIds = ['de8de156da134dabac24257f81ff282f', '2fbb5fe2e29a4d70aa5854ce7ce3e20b'];

        expect(wrapper.vm.tempProductIds).toEqual(expectedProductIds);
        expect(wrapper.vm.element.config.products.value).toBeNull();
    });

    it('should store the streamIds after changing the assignment type to "static"', async () => {
        const wrapper = await createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream',
            },
        });

        wrapper.vm.onChangeAssignmentType('static');

        await wrapper.vm.$nextTick();

        const expectedStreamId = 'de8de156da134dabac24257f81ff282f';

        expect(wrapper.vm.tempStreamId).toEqual(expectedStreamId);
        expect(wrapper.vm.element.config.products.value).toEqual([]);
    });

    it('should render product stream preview modal', async () => {
        const wrapper = await createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream',
            },
        });

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            showProductStreamPreview: true,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-product-stream-modal-preview-stub')
            .exists()).toBeTruthy();
    });
});
