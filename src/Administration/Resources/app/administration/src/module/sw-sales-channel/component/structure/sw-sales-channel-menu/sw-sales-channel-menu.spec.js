/**
 * @package buyers-experience
 */

import { mount } from '@vue/test-utils';
import { createRouter, createWebHistory } from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import getDomainLink from 'src/module/sw-sales-channel/service/domain-link.service';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/user-config',
    status: 200,
    response: {
        data: [],
    },
});

const defaultAdminLanguageId = '6a357734-afe4-4f17-a814-fb89ce9724fc';

const headlessSalesChannel = {
    id: '342112f7-ba2f-4c73-a63f-82918e67f953',
    active: true,
    domains: [],
    type: {
        id: Shopware.Defaults.apiSalesChannelTypeId,
        iconName: 'default-shopping-basket',
    },
    translated: {
        name: 'Headless',
    },
};

const storeFrontWithStandardDomain = {
    id: '8106c8da-4528-406e-8b47-dcae65965f6b',
    active: true,
    domains: [
        {
            languageId: 'ab3e5a76-9e6a-493c-bc6c-117563976bcc',
            url: 'http://shop/custom-language',
        },
        {
            languageId: Shopware.Defaults.systemLanguageId,
            url: 'http://shop/default-language',
        },
    ],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop',
    },
    translated: {
        name: 'Storefront with default domains',
    },
};

const storefrontWithoutDefaultDomain = {
    id: '0a660a4e-c1c8-4de7-a1cf-bd7a9c9886fa',
    active: true,
    domains: [
        {
            languageId: 'f084d9e0-cba4-4c42-bf99-3994e8fce125',
            url: 'http://shop/custom-language',
        },
        {
            languageId: defaultAdminLanguageId,
            url: 'http://shop/admin-language',
        },
    ],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop',
    },
    translated: {
        name: 'Storefront with non mapped domain',
    },
};

const storefrontWithoutDomains = {
    id: '613cc4f6-1ace-4fbf-867a-e4b2ade87203',
    active: true,
    domains: [],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop',
    },
    translated: {
        name: 'Storefront with non mapped domain',
    },
};

const inactiveStorefront = {
    id: 'a9237944-c347-4583-88b9-6d00719baff6',
    active: false,
    domains: [
        {
            languageId: '14383ce0-d2b6-4c44-94a7-cf71b42fa35a',
            url: 'http://shop/custom-language',
        },
        {
            languageId: defaultAdminLanguageId,
            url: 'http://shop/admin-language',
        },
    ],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop',
    },
    translated: {
        name: 'Storefront with non mapped domain',
    },
};

async function createWrapper(salesChannels = []) {
    const router = createRouter({
        history: createWebHistory(),
        routes: [
            {
                name: 'sw.sales.channel.detail',
                path: '/sw/sales/channel/detail/:id',
                component: await wrapTestComponent('sw-sales-channel-detail', {
                    sync: true,
                }),
            },
            {
                name: 'sw.sales.channel.list',
                path: '/sw/sales/channel/list',
                component: await wrapTestComponent('sw-sales-channel-list', {
                    sync: true,
                }),
            },
        ],
    });

    router.push({
        name: 'sw.sales.channel.detail',
        // the id is the storeFrontWithStandardDomain sales-channel
        params: { id: '8106c8da-4528-406e-8b47-dcae65965f6b' },
    });

    await router.isReady();

    return mount(await wrapTestComponent('sw-sales-channel-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': {
                    // eslint-disable-next-line no-template-curly-in-string
                    template: '<div :class="`sw-icon sw-icon--${name}`"></div>',
                    props: ['name'],
                },
                'sw-admin-menu-item': {
                    template:
                        '<div class="sw-admin-menu-item" :class="$attrs.class"><div>{{ entry.label }}</div><slot name="additional-text"></slot></div>',
                    props: ['entry'],
                },
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-loader': true,
                'sw-internal-link': true,
                'sw-sales-channel-modal': true,
                'router-link': true,
            },
            provide: {
                domainLinkService: {
                    getDomainLink: getDomainLink,
                },
                repositoryFactory: {
                    create: () => ({
                        search: jest.fn((criteria, context) => {
                            const salesChannelsWithLimit = salesChannels.slice(0, criteria.limit);

                            return Promise.resolve(
                                new EntityCollection(
                                    'sales-channel',
                                    'sales_channel',
                                    context,
                                    criteria,
                                    salesChannelsWithLimit,
                                    salesChannels.length,
                                    null,
                                ),
                            );
                        }),
                    }),
                },
            },
        },
    });
}

Shopware.Application.addServiceProvider('salesChannelFavorites', () => {
    const favorites = [];

    return {
        state: { favorites },
        initService() {
            favorites.length = 0;
            return Promise.resolve();
        },
        getFavoriteIds() {
            return favorites;
        },
        isFavorite(id) {
            return favorites.includes(id);
        },
        update(state, salesChannelId) {
            if (state && !this.isFavorite(salesChannelId)) {
                favorites.push(salesChannelId);
            } else if (!state && this.isFavorite(salesChannelId)) {
                const index = this.state.favorites.indexOf(salesChannelId);

                favorites.splice(index, 1);
            }
        },
    };
});

describe('src/module/sw-sales-channel/component/structure/sw-sales-channel-menu', () => {
    beforeEach(async () => {
        Shopware.Service('salesChannelFavorites').state.favorites = [];
        Shopware.State.get('session').languageId = defaultAdminLanguageId;
        global.repositoryFactoryMock.showError = false;
    });

    it('should be able to create sales channels when user has the privilege', async () => {
        global.activeAclRoles = ['sales_channel.creator'];

        const wrapper = await createWrapper();

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeTruthy();
    });

    it('should not be able to create sales channels when user has not the privilege', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeFalsy();
    });

    it('should search the right sales channels', async () => {
        const wrapper = await createWrapper();

        const parsedCriteria = wrapper.vm.salesChannelCriteria.parse();

        expect(parsedCriteria).toEqual(
            expect.objectContaining({
                associations: expect.objectContaining({
                    type: expect.any(Object),
                    domains: expect.any(Object),
                }),
            }),
        );
    });

    it('should show an entry for every sales channel returned from api', async () => {
        const testSalesChannels = [
            headlessSalesChannel,
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        const wrapper = await createWrapper(testSalesChannels);

        await flushPromises();

        const salesChannelItems = wrapper.findAll('.sw-admin-menu__sales-channel-item');

        expect(salesChannelItems).toHaveLength(testSalesChannels.length);
    });

    it('does not add a link to sales channel for non storefront sales channel', async () => {
        const wrapper = await createWrapper([headlessSalesChannel]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);
    });

    it('should use link to default language if exists', async () => {
        window.open = jest.fn();

        const wrapper = await createWrapper([storeFrontWithStandardDomain]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toHaveBeenCalledWith('http://shop/default-language', '_blank');
    });

    it('prefers link to domain with actual admin language over others', async () => {
        window.open = jest.fn();

        const wrapper = await createWrapper([storefrontWithoutDefaultDomain]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toHaveBeenCalledWith('http://shop/admin-language', '_blank');
    });

    it('takes first domain link if neither default language nor admin language exists', async () => {
        window.open = jest.fn();
        Shopware.State.get('session').languageId = Shopware.Utils.createId();

        const wrapper = await createWrapper([storefrontWithoutDefaultDomain]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toHaveBeenCalledWith('http://shop/custom-language', '_blank');
    });

    it('does not pick a storefront domain if there is none', async () => {
        const wrapper = await createWrapper([storefrontWithoutDomains]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);
    });

    it('does not show a storefront domain if storefront is not active', async () => {
        const wrapper = await createWrapper([inactiveStorefront]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);
    });

    it('shows "more" when no favourites are selected and there are more than 7 saleschannels', async () => {
        const salesChannels = [
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        for (let i = 0; i < 3; i += 1) {
            salesChannels.push({
                id: `${i}a`,
                translated: { name: `${i}a` },
                type: {
                    id: Shopware.Defaults.apiSalesChannelTypeId,
                    iconName: 'default-shopping-basket',
                },
            });
        }

        const wrapper = await createWrapper(salesChannels);

        await flushPromises();

        // check if "more" item is visible
        const moreItems = wrapper.find('.sw-admin-menu__sales-channel-more-items');
        expect(moreItems.isVisible()).toBe(true);
        expect(moreItems.text()).toContain('sw-sales-channel.general.titleMenuMoreItems');
    });

    it('shows "more" when more than 50 sales channels are available and marked as favourites', async () => {
        const salesChannels = [
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        for (let i = 0; i < 51; i += 1) {
            salesChannels.push({
                id: `${i}a`,
                translated: { name: `${i}a` },
                type: {
                    id: Shopware.Defaults.apiSalesChannelTypeId,
                    iconName: 'default-shopping-basket',
                },
            });
        }

        Shopware.Service('salesChannelFavorites').state.favorites = salesChannels.map((el) => el.id);

        const wrapper = await createWrapper(salesChannels);

        await flushPromises();

        // check if "more" item is visible
        const moreItems = wrapper.find('.sw-admin-menu__sales-channel-more-items');
        expect(moreItems.isVisible()).toBe(true);
        expect(moreItems.text()).toContain('sw-sales-channel.general.titleMenuMoreItems');
    });

    it('hide "more" when less than 7 sales channels are available and no favourites are selected', async () => {
        const wrapper = await createWrapper([
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
            {
                id: '1a',
                translated: { name: '1a' },
                type: {
                    id: Shopware.Defaults.apiSalesChannelTypeId,
                    iconName: 'default-shopping-basket',
                },
            },
            {
                id: '2b',
                translated: { name: '2b' },
                type: {
                    id: Shopware.Defaults.apiSalesChannelTypeId,
                    iconName: 'default-shopping-basket',
                },
            },
        ]);

        await flushPromises();

        // check if "more" item is hidden
        const moreItems = wrapper.find('.sw-admin-menu__sales-channel-more-items');
        expect(moreItems.exists()).toBe(false);
    });

    it('hide "more" when less than 50 sales channels are available and favourites are selected', async () => {
        const salesChannels = [
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        const wrapper = await createWrapper(salesChannels);

        Shopware.Service('salesChannelFavorites').state.favorites = salesChannels.map((el) => el.id);

        await flushPromises();

        // check if "more" item is hidden
        const moreItems = wrapper.find('.sw-admin-menu__sales-channel-more-items');
        expect(moreItems.exists()).toBe(false);
    });

    it('should only load the sales channel once when no favorites are defined', async () => {
        const salesChannels = [
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        const wrapper = await createWrapper(salesChannels);

        await flushPromises();

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(1);
    });

    it('should only load the sales channel once when also favorites are defined', async () => {
        const salesChannels = [
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            headlessSalesChannel,
            storefrontWithoutDomains,
            inactiveStorefront,
        ];

        Shopware.Service('salesChannelFavorites').state.favorites = salesChannels.map((el) => el.id);
        const wrapper = await createWrapper(salesChannels);

        await flushPromises();

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(1);
    });
});
