import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-slider/config';

function createWrapper(customCmsElementConfig) {
    const productStreamMock = {
        name: 'Cheap pc parts',
        apiFilter: ['foo', 'bar'],
        invalid: false
    };

    const productMock = {
        name: 'Small Silk Heart Worms'
    };

    return shallowMount(Shopware.Component.build('sw-cms-el-config-product-slider'), {
        propsData: {
            element: {
                config: {
                    title: {
                        value: ''
                    },
                    products: {
                        value: ['de8de156da134dabac24257f81ff282f', '2fbb5fe2e29a4d70aa5854ce7ce3e20b'],
                        source: 'static'
                    },
                    productStreamSorting: {
                        value: 'name:ASC'
                    },
                    productStreamLimit: {
                        value: 10
                    },
                    ...customCmsElementConfig
                }
            },
            defaultConfig: {}
        },
        stubs: {
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-container': true,
            'sw-field': true,
            'sw-single-select': true,
            'sw-entity-multi-select': true,
            'sw-modal': true,
            'sw-product-stream-grid-preview': true,
            'sw-entity-single-select': true,
            'sw-alert': true,
            'sw-number-field': true,
            'sw-icon': true
        },
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return {};
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        get: () => Promise.resolve(productStreamMock),
                        search: () => Promise.resolve(productMock)
                    };
                }
            }
        }
    });
}

describe('module/sw-cms/elements/product-slider/config', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render product assignment type select', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-cms-el-config-product-slider__product-assignment-type-select')
            .exists()).toBeTruthy();
    });

    it('should render manual product assignment by default', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-cms-el-config-product-slider__products').exists()).toBeTruthy();
    });

    it('should fetch manual assigned products', async () => {
        const wrapper = createWrapper();
        const productMock = {
            name: 'Small Silk Heart Worms'
        };

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productCollection).toEqual(productMock);
    });

    it('should fetch product stream when assignment type is "product_stream"', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        const expectedProductStream = {
            name: 'Cheap pc parts',
            apiFilter: ['foo', 'bar'],
            invalid: false
        };

        expect(wrapper.vm.productStream).toEqual(expectedProductStream);
    });

    it('should fetch product stream when changing product stream via select', async () => {
        const wrapper = createWrapper();

        wrapper.vm.onChangeProductStream('de8de156da134dabac24257f81ff282f');

        await wrapper.vm.$nextTick();

        const expectedProductStream = {
            name: 'Cheap pc parts',
            apiFilter: ['foo', 'bar'],
            invalid: false
        };

        expect(wrapper.vm.productStream).toEqual(expectedProductStream);
    });

    it('should set product stream to null when changing product stream via select and no stream is given', async () => {
        const wrapper = createWrapper();

        wrapper.vm.onChangeProductStream(null);

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStream).toEqual(null);
    });

    it('should render product stream selection when element product type is "product_stream"', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
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
        const wrapper = createWrapper();

        wrapper.vm.onChangeAssignmentType('product_stream');

        await wrapper.vm.$nextTick();

        const expectedProductIds = ['de8de156da134dabac24257f81ff282f', '2fbb5fe2e29a4d70aa5854ce7ce3e20b'];

        expect(wrapper.vm.tempProductIds).toEqual(expectedProductIds);
        expect(wrapper.vm.element.config.products.value).toBe(null);
    });

    it('should store the streamIds after changing the assignment type to "static"', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
        });

        wrapper.vm.onChangeAssignmentType('static');

        await wrapper.vm.$nextTick();

        const expectedStreamId = 'de8de156da134dabac24257f81ff282f';

        expect(wrapper.vm.tempStreamId).toEqual(expectedStreamId);
        expect(wrapper.vm.element.config.products.value).toEqual([]);
    });

    it('should build correct sorting criteria for stream preview including selected sorting option', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
        });

        const expectedSortingCriteria = [{ field: 'name', order: 'ASC', naturalSorting: false }];

        expect(wrapper.vm.productStreamCriteria.sortings).toEqual(expectedSortingCriteria);
    });

    it('should render product stream preview modal', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        await wrapper.setData({
            showProductStreamPreview: true
        });

        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-preview-modal')
            .exists()).toBeTruthy();
        expect(wrapper.find('sw-product-stream-grid-preview-stub')
            .exists()).toBeTruthy();
    });
});
