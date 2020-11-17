/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import 'src/app/component/structure/sw-search-bar';

const swSearchBarComponent = Shopware.Component.build('sw-search-bar');
function createWrapper(props) {
    const localVue = createLocalVue();

    return shallowMount(swSearchBarComponent, {
        localVue,
        stubs: {
            'sw-icon': true,
            'sw-version': true,
            'sw-loader': true
        },
        mocks: {
            $tc: key => key,
            $te: () => true,
            $device: {
                onResize: () => {},
                getViewportWidth: () => 1920
            },
            $route: {
                query: {
                    term: ''
                }
            }
        },
        provide: {
            searchService: {},
            searchTypeService: {
                getTypes: () => {
                    return {
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
                }
            }
        },
        propsData: props
    });
}


describe('src/app/component/structure/sw-search-bar', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        const apiService = Shopware.Application.getContainer('factory').apiService;
        apiService.register('categoryService', {
            getList: () => {
                const result = [];
                result.meta = {
                    total: 0
                };

                return Promise.resolve(result);
            }
        });
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper({
            initialSearchType: 'product'
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the tag overlay on click and not the search results', async () => {
        wrapper = await createWrapper({
            initialSearchType: 'product'
        });

        // open search
        const searchInput = wrapper.find('.sw-search-bar__input');
        await searchInput.trigger('focus');

        // check if search results are hidden and types container are visible
        const searchResults = wrapper.find('.sw-search-bar__results');
        const typesContainer = wrapper.find('.sw-search-bar__types_container');

        expect(searchResults.exists()).toBe(false);
        expect(typesContainer.exists()).toBe(true);

        // check if active type is default type
        const activeType = wrapper.find('.sw-search-bar__field .sw-search-bar__type');
        expect(activeType.text()).toBe('global.entities.product');
    });

    it('should hide the tags and not show the search results when initialSearchType and currentSearchType matches', async () => {
        wrapper = await createWrapper({
            initialSearchType: 'product'
        });

        // open search
        const searchInput = wrapper.find('.sw-search-bar__input');
        await searchInput.trigger('focus');

        // check if search results are hidden and types container are visible
        let searchResults = wrapper.find('.sw-search-bar__results');
        let typesContainer = wrapper.find('.sw-search-bar__types_container');

        expect(searchResults.exists()).toBe(false);
        expect(typesContainer.exists()).toBe(true);

        // type search value
        await searchInput.setValue('shirt');
        await flushPromises();

        const debouncedDoListSearchWithContainer = swSearchBarComponent.methods.doListSearchWithContainer;
        await debouncedDoListSearchWithContainer.flush();

        await flushPromises();

        // check if search results and types container are hidden
        searchResults = wrapper.find('.sw-search-bar__results');
        typesContainer = wrapper.find('.sw-search-bar__types_container');

        expect(searchResults.exists()).toBe(false);
        expect(typesContainer.exists()).toBe(false);
    });

    it('should hide the tags and show the search results when initialSearchType and currentSearchType are not matching', async () => {
        wrapper = await createWrapper({
            initialSearchType: 'product'
        });

        const searchInput = wrapper.find('.sw-search-bar__input');

        // open search
        await searchInput.trigger('focus');

        // check if search results are hidden and types container are visible
        let searchResults = wrapper.find('.sw-search-bar__results');
        let typesContainer = wrapper.find('.sw-search-bar__types_container');

        expect(searchResults.exists()).toBe(false);
        expect(typesContainer.exists()).toBe(true);

        // set categories as active type
        const typeItems = wrapper.findAll('.sw-search-bar__types_container .sw-search-bar__type-item');
        const secondTypeItem = typeItems.at(1);
        await secondTypeItem.trigger('click');

        // open search again
        await searchInput.trigger('focus');

        // check if new type is set
        const activeType = wrapper.find('.sw-search-bar__field .sw-search-bar__type');
        expect(activeType.text()).toBe('global.entities.category');

        // type search value
        await searchInput.setValue('shorts');
        await flushPromises();

        const debouncedDoListSearchWithContainer = swSearchBarComponent.methods.doListSearchWithContainer;
        await debouncedDoListSearchWithContainer.flush();

        await flushPromises();

        // check if search results are visible and types are hidden
        searchResults = wrapper.find('.sw-search-bar__results');
        typesContainer = wrapper.find('.sw-search-bar__types_container');

        expect(searchResults.exists()).toBe(true);
        expect(typesContainer.exists()).toBe(false);

        // check if search result is empty
        expect(searchResults.find('.sw-search-bar__results-empty-message').exists()).toBe(true);
    });
});
