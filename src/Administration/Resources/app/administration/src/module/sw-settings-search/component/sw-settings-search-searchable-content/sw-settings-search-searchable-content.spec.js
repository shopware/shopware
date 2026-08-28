/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], { featureActive = false } = {}) {
    const productSearchFieldRepository = {
        create: jest.fn(() => ({})),
        search: jest.fn(() =>
            Promise.resolve({
                total: 0,
                length: 0,
            }),
        ),
        saveAll: jest.fn(() => Promise.resolve()),
        delete: jest.fn(() => Promise.resolve()),
    };

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
                            return productSearchFieldRepository;
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
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                },

                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: [
                            'new-item-active',
                        ],
                        template: '<div class="mt-tabs"></div>',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                    },
                    'sw-tabs': {
                        name: 'sw-tabs',
                        props: [
                            'defaultItem',
                            'positionIdentifier',
                        ],
                        data() {
                            return {
                                active: this.defaultItem,
                            };
                        },
                        template: `
                            <div class="sw-tabs">
                                <slot v-bind="{ active }"></slot>
                                <slot name="content" v-bind="{ active }"></slot>
                            </div>
                        `,
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        props: [
                            'name',
                            'activeTab',
                        ],
                        template: '<button class="sw-tabs-item" type="button"><slot></slot></button>',
                    },
                    'sw-settings-search-example-modal': await wrapTestComponent('sw-settings-search-example-modal'),
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot></div>',
                    },
                    'router-link': true,
                    'sw-settings-search-searchable-content-general': {
                        name: 'sw-settings-search-searchable-content-general',
                        template: '<div class="sw-settings-search-searchable-content-general"></div>',
                    },
                    'sw-settings-search-searchable-content-customfields': {
                        name: 'sw-settings-search-searchable-content-customfields',
                        template: '<div class="sw-settings-search-searchable-content-customfields"></div>',
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content', () => {
    it('should render deprecated tabs while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('defaultItem')).toBe('general');
        expect(tabs.props('positionIdentifier')).toBe('sw-settings-search-searchable-content');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-settings-search-searchable-content-general' }).exists()).toBe(true);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper([], {
            featureActive: true,
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('defaultItem')).toBe('general');
        expect(tabs.props('positionIdentifier')).toBe('sw-settings-search-searchable-content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-settings-search.generalTab.labelGeneralTab',
                name: 'general',
            },
            {
                label: 'sw-settings-search.generalTab.labelCustomFieldsTab',
                name: 'customfields',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-settings-search-searchable-content-general' }).exists()).toBe(true);
    });

    it('should update the active content when meteor tabs emit a new active item', async () => {
        const wrapper = await createWrapper([], {
            featureActive: true,
        });

        await wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'customfields');
        await flushPromises();

        expect(wrapper.vm.defaultTab).toBe('customfields');
        expect(wrapper.findComponent({ name: 'sw-settings-search-searchable-content-general' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'sw-settings-search-searchable-content-customfields' }).exists()).toBe(true);
    });

    it('should keep parent name disabled in default configs', async () => {
        const wrapper = await createWrapper();

        const parentNameConfig = wrapper.vm.fieldConfigs.find(({ value }) => value === 'parent.name');

        expect(parentNameConfig).toEqual(
            expect.objectContaining({
                defaultConfigs: {
                    searchable: false,
                    ranking: 560,
                    tokenize: true,
                },
            }),
        );
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
