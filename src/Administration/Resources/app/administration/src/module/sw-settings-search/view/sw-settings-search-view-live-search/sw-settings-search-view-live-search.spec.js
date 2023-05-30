/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSearchViewLiveSearch from 'src/module/sw-settings-search/view/sw-settings-search-view-live-search';
import swSettingsSearchLiveSearch from 'src/module/sw-settings-search/component/sw-settings-search-live-search';
import swSettingsSearchSearchIndex from 'src/module/sw-settings-search/component/sw-settings-search-search-index';

Shopware.Component.register('sw-settings-search-view-live-search', swSettingsSearchViewLiveSearch);
Shopware.Component.register('sw-settings-search-live-search', swSettingsSearchLiveSearch);
Shopware.Component.register('sw-settings-search-search-index', swSettingsSearchSearchIndex);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-search-view-live-search'), {
        localVue,

        propsData: {
            currentSalesChannelId: null,
            searchTerms: null,
            searchResults: null,
            isLoading: false,
        },

        provide: {
            repositoryFactory: {
                create: (name) => {
                    if (name === 'product') {
                        return {
                            search: () => Promise.resolve([]),
                        };
                    }

                    if (name === 'product_search_keyword') {
                        return {
                            search: () => Promise.resolve([]),
                        };
                    }

                    return null;
                },
            },
            productIndexService: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
        },

        stubs: {
            'sw-settings-search-live-search': true,
            'sw-settings-search-search-index': await Shopware.Component.build('sw-settings-search-search-index'),
            'sw-card': true,
            'sw-button-process': true,
        },
    });
}

describe('module/sw-settings-search/view/sw-settings-search-view-live-search', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return storefrontEsEnable value', async () => {
        Shopware.Context.app.storefrontEsEnable = true;
        const wrapper = await createWrapper();

        expect(wrapper.vm.storefrontEsEnable).toBeTruthy();
    });

    it('should return default value of searchResults', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.$options.props.searchResults.default.call()).toBeNull();
    });

    it('should display rebuild search index button when user enable elasticsearch for their shop', async () => {
        Shopware.Context.app.storefrontEsEnable = false;
        const wrapper = await createWrapper();
        const rebuildSearchIndexButton = wrapper.find('.sw-settings-search__search-index-rebuild-button');

        expect(rebuildSearchIndexButton.exists()).toBeTruthy();
    });
});
