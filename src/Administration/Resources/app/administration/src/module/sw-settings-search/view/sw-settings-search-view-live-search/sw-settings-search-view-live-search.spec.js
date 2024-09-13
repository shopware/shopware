/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-search-view-live-search', {
        sync: true,
    }), {
        props: {
            currentSalesChannelId: null,
            searchTerms: null,
            searchResults: null,
            isLoading: false,
        },

        global: {
            renderStubDefaultSlot: true,

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
                'sw-settings-search-search-index': await wrapTestComponent('sw-settings-search-search-index'),
                'sw-card': true,
                'sw-button-process': true,
                'sw-alert': true,
                'sw-time-ago': true,
                'sw-progress-bar': true,
            },
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
        await flushPromises();
        const rebuildSearchIndexButton = wrapper.find('.sw-settings-search__search-index-rebuild-button');

        expect(rebuildSearchIndexButton.exists()).toBeTruthy();
    });
});
