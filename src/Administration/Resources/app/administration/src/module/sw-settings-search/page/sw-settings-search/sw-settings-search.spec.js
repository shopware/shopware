/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

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

async function createWrapper({
    featureActive = false,
    routeName = 'sw.settings.search.index.general',
    routerPush = jest.fn(),
} = {}) {
    return mount(
        await wrapTestComponent('sw-settings-search', {
            sync: true,
        }),
        {
            global: {
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
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
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
                    'sw-tabs': {
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot></slot></div>',
                        props: [
                            'positionIdentifier',
                        ],
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot></slot></div>',
                        props: [
                            'route',
                        ],
                    },
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-confirm-modal': await wrapTestComponent('sw-confirm-modal'),
                    'sw-modal': true,
                    'router-link': true,
                    'router-view': true,
                    'sw-skeleton': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
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
                    'sw-extension-component-section': true,
                },
                mocks: {
                    $route: {
                        name: routeName,
                    },
                    $router: {
                        push: routerPush,
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-search/page/sw-settings-search', () => {
    beforeEach(async () => {
        Shopware.Application.view.deleteReactive = () => {};
        global.activeAclRoles = [];
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const tabItems = wrapper.findAllComponents({ name: 'sw-tabs-item' });

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        expect(tabItems).toHaveLength(2);
        expect(tabItems[0].props('route')).toStrictEqual({ name: 'sw.settings.search.index.general' });
        expect(tabItems[0].text()).toBe('sw-settings-search.page.generalTab');
        expect(tabItems[1].props('route')).toStrictEqual({ name: 'sw.settings.search.index.liveSearch' });
        expect(tabItems[1].text()).toBe('sw-settings-search.page.liveSearchTab');
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.settings.search.index.liveSearch',
        });
        await wrapper.vm.$nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-search-header');
        expect(tabs.props('defaultItem')).toBe('sw.settings.search.index.liveSearch');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-settings-search.page.generalTab',
                name: 'sw.settings.search.index.general',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-settings-search.page.liveSearchTab',
                name: 'sw.settings.search.index.liveSearch',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.getProductSearchConfigs = jest.fn();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const generalTab = tabs.props('items').find((item) => {
            return item.name === 'sw.settings.search.index.general';
        });
        const liveSearchTab = tabs.props('items').find((item) => {
            return item.name === 'sw.settings.search.index.liveSearch';
        });

        generalTab.onClick();
        liveSearchTab.onClick();

        expect(wrapper.vm.getProductSearchConfigs).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.search.index.general' });
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.search.index.liveSearch' });
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
