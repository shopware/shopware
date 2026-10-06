/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/structure/sw-search-bar-item';
import 'src/app/component/base/sw-highlight-text';
import RecentlySearchService from 'src/app/service/recently-search.service';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

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

    async function createWrapper(props, mocks = {}) {
        swSearchBarItemComponent = await wrapTestComponent('sw-search-bar-item', { sync: true });
        spyOnClickSearchResult = jest.spyOn(swSearchBarItemComponent.methods, 'onClickSearchResult');
        jest.spyOn(swSearchBarItemComponent.methods, 'registerEvents').mockImplementation(() => {});
        jest.spyOn(swSearchBarItemComponent.methods, 'removeEvents').mockImplementation(() => {});
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');

        return mount(swSearchBarItemComponent, {
            global: {
                mocks,
                stubs: {
                    'sw-highlight-text': true,
                    'sw-shortcut-overview-item': true,
                    'router-link': {
                        emits: ['click'],
                        template: '<div class="sw-router-link" @click="$emit(\'click\', $event)"><slot></slot></div>',
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
        swSearchBarItemComponent = await wrapTestComponent('sw-search-bar-item', { sync: true });
        recentlySearchService = new RecentlySearchService();
        spyOnClickSearchResult = jest.spyOn(swSearchBarItemComponent.methods, 'onClickSearchResult');
        spyRecentlySearchServiceAdd = jest.spyOn(recentlySearchService, 'add');
    });

    beforeEach(async () => {
        Shopware.Store.get('session').setCurrentUser({
            id: 'userId',
        });
    });

    it('should add clicked search result into recently search stack', async () => {
        wrapper = await createWrapper({
            entityIconName: 'regular-shopping-basket',
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

        expect(wrapper.find('.sw-search-bar-item__link').exists()).toBe(true);

        await wrapper.find('.sw-search-bar-item__link').trigger('click');

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

    describe('module icon colors', () => {
        const moduleItem = {
            entityIconName: 'regular-shopping-basket',
            entityIconColor: 'var(--sw-color-module-green-default)',
            column: 1,
            index: 1,
            type: 'module',
            item: {
                name: 'sw-order',
                color: 'var(--sw-color-module-purple-default)',
                icon: 'regular-shopping-bag',
                route: 'sw.order.index',
            },
        };

        afterEach(() => {
            useModuleIconColors().enabled.value = false;
        });

        it('should use the neutral icon color by default', async () => {
            wrapper = await createWrapper(moduleItem);

            expect(wrapper.vm.iconColor).toBe('var(--color-icon-primary-default)');
        });

        it('should use the module color when the preference is enabled', async () => {
            useModuleIconColors().enabled.value = true;
            wrapper = await createWrapper(moduleItem);

            expect(wrapper.vm.iconColor).toBe('var(--sw-color-module-purple-default)');
        });

        it('should fall back to the entity icon color for entity results', async () => {
            useModuleIconColors().enabled.value = true;
            wrapper = await createWrapper({
                ...moduleItem,
                type: 'product',
                item: { id: 'productId', name: 'Awesome Product' },
            });

            expect(wrapper.vm.iconColor).toBe('var(--sw-color-module-green-default)');
        });
    });

    describe('shortcut', () => {
        // The real shortcut values live in the global snippets, which are not loaded in the
        // jest i18n instance. Mock $te/$t so the computed logic can be tested deterministically.
        const shortcutMocks = (value, exists = true) => ({
            $te: () => exists,
            $t: (_key, count) => value(count),
        });

        it('should resolve the shortcut for the module navigation state', async () => {
            wrapper = await createWrapper(
                {
                    entityIconName: '',
                    entityIconColor: '',
                    column: 1,
                    index: 1,
                    type: 'module',
                    item: {
                        name: 'product',
                        route: 'sw.product.index',
                    },
                },
                shortcutMocks((count) => (count === 2 ? 'A P' : 'G P')),
            );

            expect(wrapper.vm.shortcut).toBe('G P');
            expect(wrapper.find('sw-shortcut-overview-item-stub').exists()).toBe(true);
        });

        it('should not render a shortcut when the resolved value is the &nbsp; placeholder', async () => {
            // "Add new landing page" reuses the category module (name: 'category') with
            // action: true, whose "add" shortcut is the `&nbsp;` placeholder ("G C | &nbsp;").
            wrapper = await createWrapper(
                {
                    entityIconName: '',
                    entityIconColor: '',
                    column: 1,
                    index: 1,
                    type: 'module',
                    item: {
                        name: 'category',
                        entity: 'landing_page',
                        action: true,
                    },
                },
                shortcutMocks((count) => (count === 2 ? '&nbsp;' : 'G C')),
            );

            expect(wrapper.vm.shortcut).toBe(false);
            expect(wrapper.find('sw-shortcut-overview-item-stub').exists()).toBe(false);
        });

        it('should treat a &nbsp; placeholder with surrounding whitespace as no shortcut', async () => {
            // e.g. sales-channel navigation state: "&nbsp; | A S".
            wrapper = await createWrapper(
                {
                    entityIconName: '',
                    entityIconColor: '',
                    column: 1,
                    index: 1,
                    type: 'module',
                    item: {
                        name: 'sales-channel',
                    },
                },
                shortcutMocks((count) => (count === 2 ? 'A S' : '&nbsp; ')),
            );

            expect(wrapper.vm.shortcut).toBe(false);
            expect(wrapper.find('sw-shortcut-overview-item-stub').exists()).toBe(false);
        });

        it('should not render a shortcut when the module has no shortcut snippet', async () => {
            wrapper = await createWrapper(
                {
                    entityIconName: '',
                    entityIconColor: '',
                    column: 1,
                    index: 1,
                    type: 'module',
                    item: {
                        name: 'unknown-module',
                        route: 'sw.unknown.index',
                    },
                },
                shortcutMocks(() => 'ignored', false),
            );

            expect(wrapper.vm.shortcut).toBe(false);
            expect(wrapper.find('sw-shortcut-overview-item-stub').exists()).toBe(false);
        });
    });

    it('should return filters from filter registry', async () => {
        wrapper = await createWrapper({
            entityIconName: 'regular-shopping-basket',
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
