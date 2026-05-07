/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

const mockData = [
    {
        andLogic: false,
        minSearchLength: 4,
        excludedTerms: [],
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    },
    {
        andLogic: true,
        minSearchLength: 4,
        excludedTerms: [],
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20c',
    },
];

async function createWrapper() {
    const router = createRouter({
        history: createWebHashHistory(),
        routes: [
            {
                name: 'sw.settings.search.index.general',
                path: '/sw/settings/search/index/general',
                component: await wrapTestComponent('sw-settings-search', {
                    sync: true,
                }),
            },
            {
                name: 'sw.settings.search.index.liveSearch',
                path: '/sw/settings/search/index/live-search/',
            },
        ],
    });

    return mount(
        await wrapTestComponent('sw-settings-search', {
            sync: true,
        }),
        {
            global: {
                router,

                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(new EntityCollection('', '', Context.api, null, mockData));
                            },
                            save: (productSearchConfigs) => {
                                if (!productSearchConfigs) {
                                    return Promise.reject({ error: 'Error' });
                                }
                                return Promise.resolve();
                            },
                            create: jest.fn(() => {
                                return {};
                            }),
                        }),
                    },
                },

                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-confirm-modal': await wrapTestComponent('sw-confirm-modal'),
                    'sw-modal': true,
                    'router-link': true,
                    'router-view': true,
                    'sw-skeleton': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                default: '',
                            },
                            routeTabs: {
                                type: Boolean,
                                default: false,
                            },
                        },
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                    'sw-extension-component-section': true,
                },
            },
        },
    );
}

describe('module/sw-settings-search/page/sw-settings-search', () => {
    beforeEach(async () => {
        Shopware.Application.view.deleteReactive = () => {};
        global.activeAclRoles = [];
        global.activeFeatureFlags = [];
    });

    it('should render route-backed mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const routerPush = jest.spyOn(wrapper.vm.$router, 'push').mockResolvedValue();
        wrapper.vm.getProductSearchConfigs = jest.fn();
        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-settings-search-header');
        expect(tabs.props('defaultItem')).toBe('sw.settings.search.index.general');
        expect(tabs.props('routeTabs')).toBe(true);

        const items = tabs.props('items');
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-settings-search.page.generalTab',
                name: 'sw.settings.search.index.general',
            }),
            expect.objectContaining({
                label: 'sw-settings-search.page.liveSearchTab',
                name: 'sw.settings.search.index.liveSearch',
            }),
        ]);

        items[0].onClick();
        expect(wrapper.vm.getProductSearchConfigs).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.search.index.general' });
    });

    it('should not able to save product search config without editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBeDefined();
    });

    it('should able to save product search config if having editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-search__button-save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be show successful notification when save configuration is succeed', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getProductSearchConfigs = jest.fn();
        wrapper.vm.productSearchConfigs = {
            andLogic: true,
            minSearchLength: 2,
        };

        await wrapper.vm.onSaveSearchSettings();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.getProductSearchConfigs).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.saveSuccess',
        });
    });

    it('should be show error notification when save configuration is failed', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.productSearchConfigs = null;

        await wrapper.vm.onSaveSearchSettings();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.saveError',
        });
        expect(wrapper.vm.createNotificationSuccess).not.toHaveBeenCalled();
    });

    it('should assign new value when the new language was switch', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();

        await wrapper.vm.getProductSearchConfigs();

        expect(wrapper.vm.productSearchConfigs.andLogic).toBe(mockData[0].andLogic);
        expect(wrapper.vm.productSearchConfigs.minSearchLength).toBe(mockData[0].minSearchLength);
        expect(wrapper.vm.productSearchConfigs.excludedTerms).toHaveLength(0);
        expect(wrapper.vm.productSearchConfigs.languageId).toBe('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
    });
});
