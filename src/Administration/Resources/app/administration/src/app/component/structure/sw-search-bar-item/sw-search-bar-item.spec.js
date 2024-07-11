/**
 * @package admin
 * @group disabledCompat
 */

/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-search-bar-item';
import 'src/app/component/base/sw-highlight-text';
import RecentlySearchService from 'src/app/service/recently-search.service';

const searchTypeServiceTypes = {
    product: {
        entityName: 'product',
        entityService: 'productService',
        placeholderSnippet: 'sw-product.general.placeholderSearchBar',
        listingRoute: 'sw.product.index',
    },
    category: {
        entityName: 'category',
        entityService: 'categoryService',
        placeholderSnippet: 'sw-category.general.placeholderSearchBar',
        listingRoute: 'sw.category.index',
    },
    customer: {
        entityName: 'customer',
        entityService: 'customerService',
        placeholderSnippet: 'sw-customer.general.placeholderSearchBar',
        listingRoute: 'sw.customer.index',
    },
    order: {
        entityName: 'order',
        entityService: 'orderService',
        placeholderSnippet: 'sw-order.general.placeholderSearchBar',
        listingRoute: 'sw.order.index',
    },
    media: {
        entityName: 'media',
        entityService: 'mediaService',
        placeholderSnippet: 'sw-media.general.placeholderSearchBar',
        listingRoute: 'sw.media.index',
    },
};

describe('src/app/component/structure/sw-search-bar-item', () => {
    /** @type Wrapper */
    let wrapper;
    let swSearchBarItemComponent;
    let recentlySearchService;
    let spyOnClickSearchResult;
    let spyRecentlySearchServiceAdd;

    async function createWrapper(props) {
        swSearchBarItemComponent = await Shopware.Component.build('sw-search-bar-item');
        spyOnClickSearchResult = jest.spyOn(swSearchBarItemComponent.methods, 'onClickSearchResult');
        jest.spyOn(swSearchBarItemComponent.methods, 'registerEvents').mockImplementation(() => {});
        jest.spyOn(swSearchBarItemComponent.methods, 'removeEvents').mockImplementation(() => {});
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');

        return mount(swSearchBarItemComponent, {
            global: {
                stubs: {
                    'sw-icon': true,
                    'sw-highlight-text': true,
                    'sw-shortcut-overview-item': true,
                    'router-link': {
                        template: '<div class="sw-router-link"><slot></slot></div>',
                        props: ['to'],
                    },
                },
                provide: {
                    recentlySearchService,
                    searchTypeService: {
                        getTypes: () => searchTypeServiceTypes,
                    },
                },
            },
            props,
        });
    }

    beforeAll(async () => {
        swSearchBarItemComponent = await Shopware.Component.build('sw-search-bar-item');
        recentlySearchService = new RecentlySearchService();
        spyOnClickSearchResult = jest.spyOn(swSearchBarItemComponent.methods, 'onClickSearchResult');
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');
    });

    beforeEach(async () => {
        Shopware.State.get('session').currentUser = {
            id: 'userId',
        };
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper({
            entityIconName: 'default-shopping-basket',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'product',
            item: {
                id: 'productId',
                name: 'Awesome Product',
            },
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should add clicked search result into recently search stack', async () => {
        wrapper = await createWrapper({
            entityIconName: 'default-shopping-basket',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'product',
            item: {
                id: 'productId',
                name: 'Awesome Product',
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-router-link').exists()).toBe(true);

        await wrapper.find('.sw-router-link').trigger('click');

        expect(spyOnClickSearchResult).toHaveBeenCalledTimes(1);
        expect(spyOnClickSearchResult).toHaveBeenCalledWith('product', 'productId');

        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledTimes(1);
        expect(spyRecentlySearchServiceAdd).toHaveBeenCalledWith('userId', 'product', 'productId', {});
    });

    it('should get correct name of variant products', async () => {
        wrapper = await createWrapper({
            item: {
                name: null,
                id: '1001',
                parentId: '1000',
                variation: [
                    { group: 'color', option: 'red' },
                    { group: 'size', option: '39' },
                ],
                translated: { name: 'Product test' },
            },
            index: 1,
            type: '',
            column: 1,
            searchTerm: null,
            entityIconColor: '',
            entityIconName: '',
        });

        expect(wrapper.vm.productDisplayName).toBe('Product test (color: red | size: 39)');
    });

    it('should return filters from filter registry', async () => {
        wrapper = await createWrapper({
            entityIconName: 'default-shopping-basket',
            entityIconColor: 'blue',
            column: 1,
            index: 1,
            type: 'product',
            item: {
                id: 'productId',
                name: 'Awesome Product',
            },
        });

        expect(wrapper.vm.mediaNameFilter).toEqual(expect.any(Function));
    });
});
