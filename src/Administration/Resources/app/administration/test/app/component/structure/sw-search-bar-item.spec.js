/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/structure/sw-search-bar-item';
import 'src/app/component/base/sw-highlight-text';
import RecentlySearchService from 'src/app/service/recently-search.service';

const swSearchBarItemComponent = Shopware.Component.build('sw-search-bar-item');
const recentlySearchService = new RecentlySearchService();
const spyOnClickSearchResult = jest.spyOn(swSearchBarItemComponent.methods, 'onClickSearchResult');
const spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');

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

function createWrapper(props) {
    const localVue = createLocalVue();

    return shallowMount(swSearchBarItemComponent, {
        localVue,
        stubs: {
            'sw-icon': true,
            'sw-highlight-text': true,
            'sw-shortcut-overview-item': true,
            'router-link': {
                template: '<div class="sw-router-link"><slot></slot></div>',
                props: ['to']
            }
        },
        propsData: props,
        provide: {
            recentlySearchService,
            searchTypeService: {
                getTypes: () => searchTypeServiceTypes
            }
        },
        computed: {
            currentUser() {
                return {
                    id: 'userId'
                };
            }
        }
    });
}

describe('src/app/component/structure/sw-search-bar-item', () => {
    /** @type Wrapper */
    let wrapper;

    it('should be a Vue.js component', async () => {
        wrapper = createWrapper({
            entityIconName: 'default-shopping-basket',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'product',
            item: {
                id: 'productId',
                name: 'Awesome Product'
            }
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should add clicked search result into recently search stack', async () => {
        wrapper = createWrapper({
            entityIconName: 'default-shopping-basket',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'product',
            item: {
                id: 'productId',
                name: 'Awesome Product'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-router-link').exists()).toBe(true);

        wrapper.find('.sw-router-link').trigger('click');

        expect(spyOnClickSearchResult).toHaveBeenCalledTimes(1);
        expect(spyOnClickSearchResult).toHaveBeenCalledWith('product', 'productId');

        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledTimes(1);
        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledWith('userId', 'product', 'productId', {});
    });

    it('should get correct name of variant products', async () => {
        wrapper = createWrapper({
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
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productDisplayName).toEqual('Product test (color: red | size: 39)');
    });
});
