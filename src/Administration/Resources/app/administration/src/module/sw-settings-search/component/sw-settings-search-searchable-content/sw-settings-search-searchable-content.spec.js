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
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: '',
                            },
                            defaultItem: {
                                type: String,
                                required: false,
                                default: '',
                            },
                        },
                        emits: [
                            'new-item-active',
                            'extension-item-active',
                        ],
                        template: '<div class="mt-tabs-stub"></div>',
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
        global.activeFeatureFlags = [];
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

    it('should render Meteor tabs when the feature is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        wrapper.vm.loadData = jest.fn();

        const mtTabs = wrapper.getComponent('.mt-tabs-stub');

        expect(mtTabs.props('positionIdentifier')).toBe('sw-settings-search-searchable-content');
        expect(mtTabs.props('defaultItem')).toBe('general');
        expect(mtTabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-settings-search.generalTab.labelGeneralTab',
                name: 'general',
            }),
            expect.objectContaining({
                label: 'sw-settings-search.generalTab.labelCustomFieldsTab',
                name: 'customfields',
            }),
        ]);

        mtTabs.vm.$emit('new-item-active', 'customfields');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.defaultTab).toBe('customfields');
        expect(wrapper.vm.loadData).toHaveBeenCalled();

        wrapper.vm.loadData.mockClear();
        mtTabs.vm.$emit('extension-item-active', 'extension-tab');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.defaultTab).toBe('extension-tab');
        expect(wrapper.vm.loadData).not.toHaveBeenCalled();
    });
});
