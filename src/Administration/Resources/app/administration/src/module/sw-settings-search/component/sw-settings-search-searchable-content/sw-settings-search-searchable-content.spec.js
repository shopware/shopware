/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-search-searchable-content', {
            sync: true,
        }),
        {
            props: {
                searchConfigId: '',
            },

            global: {
                provide: {
                    repositoryFactory: {
                        create() {
                            return Promise.resolve();
                        },
                    },
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
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        props: [
                            'items',
                            'defaultItem',
                            'positionIdentifier',
                        ],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                    },
                    'sw-settings-search-example-modal': await wrapTestComponent('sw-settings-search-example-modal'),
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot></div>',
                    },
                    'router-link': true,
                    'sw-settings-search-searchable-content-general': true,
                    'sw-settings-search-searchable-content-customfields': true,
                },
            },
        },
    );
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [''];
    });

    it('should render legacy tabs when major tabs migration is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-tabs-stub').exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render mt-tabs with searchable content items when major tabs migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-settings-search.generalTab.labelGeneralTab',
                name: 'general',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-settings-search.generalTab.labelCustomFieldsTab',
                name: 'customfields',
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('general');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-settings-search-searchable-content');
        expect(wrapper.find('sw-tabs-stub').exists()).toBe(false);
    });

    it('should switch active major tab content through item click handlers', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        wrapper.vm.loadData = jest.fn();

        wrapper.getComponent('mt-tabs-stub').props('items')[1].onClick();

        expect(wrapper.vm.defaultTab).toBe('customfields');
        expect(wrapper.vm.loadData).toHaveBeenCalled();
    });

    it('Should be show example modal when the link was clicked', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await wrapper.vm.$nextTick();

        const linkElement = wrapper.find('.sw-settings-search__searchable-content-show-example-link');

        await linkElement.trigger('click');
        expect(wrapper.vm.showExampleModal).toBe(true);

        await wrapper.vm.onShowExampleModal();
        await flushPromises();
        const modalElement = wrapper.find('.sw-settings-search-example-modal');
        expect(modalElement.isVisible()).toBe(true);
    });

    it('Should not able to reset to default without editor privilege', async () => {
        const wrapper = await createWrapper([
            'product_search_config.viewer',
        ]);
        await flushPromises();

        const resetButton = wrapper.find('.sw-settings-search__searchable-content-reset-button');
        expect(resetButton.attributes().disabled).toBeDefined();
    });

    it('Should able to reset to default if having editor privilege', async () => {
        const wrapper = await createWrapper([
            'product_search_config.editor',
        ]);
        await wrapper.vm.$nextTick();

        const resetButton = wrapper.find('.sw-settings-search__searchable-content-reset-button');

        wrapper.vm.isEnabledReset = false;
        await wrapper.vm.$nextTick();

        expect(resetButton.isVisible()).toBe(true);
        expect(resetButton.attributes().disabled).toBeFalsy();
    });

    it('should return storefrontEsEnable value', async () => {
        Shopware.Context.app.storefrontEsEnable = true;
        const wrapper = await createWrapper();

        expect(wrapper.vm.storefrontEsEnable).toBeTruthy();
    });
});
