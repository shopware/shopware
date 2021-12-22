import { shallowMount } from '@vue/test-utils';
import 'src/app/component/structure/sw-search-bar-item';

const searchTypeServiceTypes = {
    product: {
        entityName: 'product',
        entityService: 'productService',
        placeholderSnippet: 'sw-product.general.placeholderSearchBar',
        listingRoute: 'sw.product.index'
    },
    category: {
        entityName: 'category',
        entityService: 'categoryService',
        placeholderSnippet: 'sw-category.general.placeholderSearchBar',
        listingRoute: 'sw.category.index'
    },
    customer: {
        entityName: 'customer',
        entityService: 'customerService',
        placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
        listingRoute: 'sw.customer.index'
    },
    order: {
        entityName: 'order',
        entityService: 'orderService',
        placeholderSnippet: 'sw-order.general.placeholderSearchBar',
        listingRoute: 'sw.order.index'
    },
    media: {
        entityName: 'media',
        entityService: 'mediaService',
        placeholderSnippet: 'sw-media.general.placeholderSearchBar',
        listingRoute: 'sw.media.index'
    }
};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-search-bar-item'), {
        propsData: {
            item: {
                name: null,
                id: '1001',
                parentId: '1000',
                variation: [
                    { group: 'color', option: 'red' },
                    { group: 'size', option: '39' }
                ],
                translated: { name: 'Product test' }
            },
            index: 1,
            type: '',
            column: 1,
            searchTerm: null,
            entityIconColor: '',
            entityIconName: ''
        },
        provide: {
            searchTypeService: {
                getTypes: () => searchTypeServiceTypes
            }
        }
    });
}

describe('src/app/component/structure/sw-search-bar-item', () => {
    let wrapper;

    it('should get correct name of variant products', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_6040'];

        wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productDisplayName).toEqual('Product test (color: red | size: 39)');
    });
});
