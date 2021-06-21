import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-slider/config';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/select/base/sw-select-result-list';


const productMock = [{
    id: 'de8de156da134dabac24257f81ff282f',
    name: 'Translated',
    translated: {
        name: 'Übersetzt'
    }
}, {
    id: 'c336e6ad6a174c76bb201ce7ba0e2ab3',
    name: 'Test',
    translated: {}
}];


const productStreamMock = {
    name: 'Cheap pc parts',
    apiFilter: ['foo', 'bar'],
    invalid: false
};


function createWrapper(customCmsElementConfig) {
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
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': true,
            'sw-product-variant-info': true,
            'sw-label': true,
            'sw-modal': true,
            'sw-block-field': true,
            'sw-product-stream-grid-preview': true,
            'sw-entity-single-select': true,
            'sw-alert': true,
            'sw-number-field': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-popover': true
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
                        search: (criteria) => {
                            const products = criteria.ids.length ? productMock.slice(0, 1) : productMock;

                            products.has = id => products.some(i => i.id === id);

                            return Promise.resolve(products);
                        }
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

    it('should display check marks for manually selected products', async () => {
        const wrapper = createWrapper();


        expect(wrapper.find('.sw-cms-el-config-product-slider__products').exists()).toBeTruthy();

        wrapper.vm.element.config.products.source = 'manual';

        await wrapper.get('.sw-select-selection-list__input').trigger('click');
        await wrapper.vm.$nextTick();

        const selectResults = wrapper.findAll('.sw-select-result-list__item-list > sw-select-result-stub');

        expect(selectResults.at(0).attributes().selected).toBe('true');
        expect(selectResults.at(0).text()).toBe(productMock[0].translated.name);

        expect(selectResults.at(1).attributes().selected).toBeFalsy();
        expect(selectResults.at(1).text()).toBe(productMock[1].name);
    });

    it('should fetch product stream when assignment type is "product_stream"', async () => {
        const wrapper = createWrapper({
            products: {
                value: 'de8de156da134dabac24257f81ff282f',
                source: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStream).toEqual(productStreamMock);
    });

    it('should fetch product stream when changing product stream via select', async () => {
        const wrapper = createWrapper();

        wrapper.vm.onChangeProductStream('de8de156da134dabac24257f81ff282f');

        await wrapper.vm.$nextTick();


        expect(wrapper.vm.productStream).toEqual(productStreamMock);
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

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-cms-el-config-product-slider__product-stream-preview-modal')
            .exists()).toBeTruthy();
        expect(wrapper.find('sw-product-stream-grid-preview-stub')
            .exists()).toBeTruthy();
    });
});
