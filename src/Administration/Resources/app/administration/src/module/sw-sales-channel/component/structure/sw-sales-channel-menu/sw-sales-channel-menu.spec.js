/**
 * @package buyers-experience
 */

import { mount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/sw-sales-channel/component/structure/sw-sales-channel-menu';
import 'src/app/component/base/sw-icon';
import 'src/app/component/structure/sw-admin-menu-item';
import 'src/module/sw-sales-channel/service/sales-channel-favorites.service';
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
    domains: [{
        languageId: 'ab3e5a76-9e6a-493c-bc6c-117563976bcc',
        url: 'http://shop/custom-language',
    }, {
        languageId: Shopware.Defaults.systemLanguageId,
        url: 'http://shop/default-language',
    }],
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
    domains: [{
        languageId: 'f084d9e0-cba4-4c42-bf99-3994e8fce125',
        url: 'http://shop/custom-language',
    }, {
        languageId: defaultAdminLanguageId,
        url: 'http://shop/admin-language',
    }],
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
    domains: [{
        languageId: '14383ce0-d2b6-4c44-94a7-cf71b42fa35a',
        url: 'http://shop/custom-language',
    }, {
        languageId: defaultAdminLanguageId,
        url: 'http://shop/admin-language',
    }],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop',
    },
    translated: {
        name: 'Storefront with non mapped domain',
    },
};

async function createWrapper(salesChannels = [], privileges = []) {
    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const router = new VueRouter({
        routes: [{
            name: 'sw.sales.channel.detail',
            path: '/sw/sales/channel/detail/:id',
            component: localVue.component('sw-sales-channel-detail', {
                name: 'sw-sales-channel-detail',
                template: '<div class="sw-sales-channel-detail"></div>',
            }),
        }, {
            name: 'sw.sales.channel.list',
            path: '/sw/sales/channel/list',
            component: localVue.component('sw-sales-channel-list', {
                name: 'sw-sales-channel-list',
                template: '<div class="sw-sales-channel-list"></div>',
            }),
        }],
    });

    router.push({
        name: 'sw.sales.channel.detail',
        // the id is the storeFrontWithStandardDomain sales-channel
        params: { id: '8106c8da-4528-406e-8b47-dcae65965f6b' },
    });

    return mount(await Shopware.Component.build('sw-sales-channel-menu'), {
        localVue,
        router,
        stubs: {
            // eslint does not allow vue js templating syntax when used in a string
            // eslint-disable-next-line no-template-curly-in-string
            'sw-icon': { props: ['name'], template: '<div :class="`sw-icon sw-icon--${name}`"></div>' },
            'sw-admin-menu-item': await Shopware.Component.build('sw-admin-menu-item'),
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-loader': true,
            'sw-internal-link': true,
        },
        provide: {
            domainLinkService: {
                getDomainLink: getDomainLink,
            },
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                },
            },
            repositoryFactory: {
                create: () => ({
                    search: jest.fn((criteria, context) => {
                        const salesChannelsWithLimit = salesChannels.slice(0, criteria.limit);

                        return Promise.resolve(new EntityCollection(
                            'sales-channel',
                            'sales_channel',
                            context,
                            criteria,
                            salesChannelsWithLimit,
                            salesChannels.length,
                            null,
                        ));
                    }),
                }),
            },
        },
    });
}

describe('src/module/sw-sales-channel/component/structure/sw-sales-channel-menu', () => {
    beforeEach(async () => {
        Shopware.State.get('session').languageId = defaultAdminLanguageId;
        Shopware.State.get('session').currentUser = { id: '8fe88c269c214ea68badf7ebe678ab96' };
        Shopware.Service('salesChannelFavorites').state.favorites = [];

        global.repositoryFactoryMock.showError = false;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        wrapper.destroy();
    });

    it('should be able to create sales channels when user has the privilege', async () => {
        const wrapper = await createWrapper(
            [],
            [
                'sales_channel.creator',
            ],
        );

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeTruthy();

        wrapper.destroy();
    });

    it('should not be able to create sales channels when user has not the privilege', async () => {
        const wrapper = await createWrapper();

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeFalsy();

        wrapper.destroy();
    });

    it('should search the right sales channels', async () => {
        const wrapper = await createWrapper();

        const parsedCriteria = wrapper.vm.salesChannelCriteria.parse();

        expect(parsedCriteria).toEqual(expect.objectContaining({
            associations: expect.objectContaining({
                type: expect.any(Object),
                domains: expect.any(Object),
            }),
        }));

        wrapper.destroy();
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

        wrapper.destroy();
    });

    it('should use link to default language if exists', async () => {
        window.open = jest.fn();

        const wrapper = await createWrapper([storeFrontWithStandardDomain]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toHaveBeenCalledWith('http://shop/default-language', '_blank');

        wrapper.destroy();
    });

    it('prefers link to domain with actual admin language over others', async () => {
        window.open = jest.fn();

        const wrapper = await createWrapper([storefrontWithoutDefaultDomain]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toHaveBeenCalledWith('http://shop/admin-language', '_blank');

        wrapper.destroy();
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

        wrapper.destroy();
    });

    it('does not pick a storefront domain if there is none', async () => {
        const wrapper = await createWrapper([storefrontWithoutDomains]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);

        wrapper.destroy();
    });

    it('does not show a storefront domain if storefront is not active', async () => {
        const wrapper = await createWrapper([inactiveStorefront]);

        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);

        wrapper.destroy();
    });

    it('shows just one saleschannel as selected', async () => {
        const wrapper = await createWrapper([storeFrontWithStandardDomain, headlessSalesChannel]);

        await flushPromises();

        const links = wrapper.findAll('.sw-admin-menu__navigation-link');

        const activeLinkClasses = ['router-link-active', ' router-link-active'];

        expect(links.at(0).classes().some(cssClass => activeLinkClasses.includes(cssClass))).toBe(true);
        expect(links.at(1).classes().some(cssClass => activeLinkClasses.includes(cssClass))).toBe(false);

        wrapper.destroy();
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
        wrapper.destroy();
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
        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(0);

        await flushPromises();

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(1);

        wrapper.destroy();
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

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(0);

        await flushPromises();

        expect(wrapper.vm.salesChannelRepository.search).toHaveBeenCalledTimes(1);

        wrapper.destroy();
    });
});
