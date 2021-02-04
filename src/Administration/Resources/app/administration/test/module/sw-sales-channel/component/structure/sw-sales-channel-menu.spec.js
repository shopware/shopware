import { mount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import flushPromises from 'flush-promises';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/sw-sales-channel/component/structure/sw-sales-channel-menu';
import 'src/app/component/base/sw-icon';
import 'src/app/component/structure/sw-admin-menu-item';

const defaultAdminLanguageId = Shopware.Utils.createId();

const headlessSalesChannel = {
    id: Shopware.Utils.createId(),
    active: true,
    domains: [],
    type: {
        id: Shopware.Defaults.apiSalesChannelTypeId,
        iconName: 'default-shopping-basket'
    },
    translated: {
        name: 'Headless'
    }
};

const storeFrontWithStandardDomain = {
    id: Shopware.Utils.createId(),
    active: true,
    domains: [{
        languageId: Shopware.Utils.createId(),
        url: 'http://shop/custom-language'
    }, {
        languageId: Shopware.Defaults.systemLanguageId,
        url: 'http://shop/default-language'
    }],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop'
    },
    translated: {
        name: 'Storefront with default domains'
    }
};

const storefrontWithoutDefaultDomain = {
    id: Shopware.Utils.createId(),
    active: true,
    domains: [{
        languageId: Shopware.Utils.createId(),
        url: 'http://shop/custom-language'
    }, {
        languageId: defaultAdminLanguageId,
        url: 'http://shop/admin-language'
    }],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop'
    },
    translated: {
        name: 'Storefront with non mapped domain'
    }
};

const storefrontWithoutDomains = {
    id: Shopware.Utils.createId(),
    active: true,
    domains: [],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop'
    },
    translated: {
        name: 'Storefront with non mapped domain'
    }
};

const inactiveStorefront = {
    id: Shopware.Utils.createId(),
    active: false,
    domains: [{
        languageId: Shopware.Utils.createId(),
        url: 'http://shop/custom-language'
    }, {
        languageId: defaultAdminLanguageId,
        url: 'http://shop/admin-language'
    }],
    type: {
        id: Shopware.Defaults.storefrontSalesChannelTypeId,
        iconName: 'default-building-shop'
    },
    translated: {
        name: 'Storefront with non mapped domain'
    }
};

function createWrapper(salesChannels = [], privileges = []) {
    const localVue = createLocalVue();
    localVue.use(VueRouter);

    const router = new VueRouter({
        routes: [{
            name: 'sw.sales.channel.detail',
            path: '/sw/sales/channel/detail/:id',
            component: localVue.component('sw-sales-channel-detail', {
                name: 'sw-sales-channel-detail',
                template: '<div class="sw-sales-channel-detail"></div>'
            })
        }]
    });

    return mount(Shopware.Component.build('sw-sales-channel-menu'), {
        localVue,
        router,
        stubs: {
            // eslint does not allow vue js templating syntax when used in a string
            // eslint-disable-next-line no-template-curly-in-string
            'sw-icon': { props: ['name'], template: '<div :class="`sw-icon sw-icon--${name}`"></div>' },
            'sw-admin-menu-item': Shopware.Component.build('sw-admin-menu-item')
        },
        provide: {
            acl: {
                can: (privilegeKey) => {
                    if (!privilegeKey) { return true; }

                    return privileges.includes(privilegeKey);
                }
            },
            repositoryFactory: {
                create: () => ({
                    search: (criteria, context) => Promise.resolve(new EntityCollection(
                        'sales-channel',
                        'sales_channel',
                        context,
                        criteria,
                        salesChannels,
                        salesChannels.length,
                        null
                    ))
                })
            }
        },
        mocks: {
            $tc: v => v
        }
    });
}

describe('module/sw-sales-channel/component/structure/sw-admin-menu-extension', () => {
    beforeEach(() => {
        Shopware.State.get('session').languageId = defaultAdminLanguageId;
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
        wrapper.destroy();
    });

    it('should be able to create sales channels when user has the privilege', async () => {
        const wrapper = createWrapper(
            [],
            [
                'sales_channel.creator'
            ]
        );

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeTruthy();

        wrapper.destroy();
    });

    it('should not be able to create sales channels when user has not the privilege', async () => {
        const wrapper = createWrapper();

        const buttonCreateSalesChannel = wrapper.find('.sw-admin-menu__headline-action');
        expect(buttonCreateSalesChannel.exists()).toBeFalsy();

        wrapper.destroy();
    });

    it('should search the right sales channels', () => {
        const wrapper = createWrapper();

        const parsedCriteria = wrapper.vm.salesChannelCriteria.parse();

        expect(parsedCriteria).toEqual(expect.objectContaining({
            associations: expect.objectContaining({
                type: expect.any(Object),
                domains: expect.any(Object)
            })
        }));

        wrapper.destroy();
    });

    it('should show an entry for every sales channel returned from api', async () => {
        const testSalesChannels = [
            headlessSalesChannel,
            storeFrontWithStandardDomain,
            storefrontWithoutDefaultDomain,
            storefrontWithoutDomains,
            inactiveStorefront
        ];

        const wrapper = await createWrapper(testSalesChannels);
        await flushPromises();

        const salesChannelItems = wrapper.findAll('.sw-admin-menu__sales-channel-item');

        expect(salesChannelItems).toHaveLength(testSalesChannels.length);
    });

    it('It does not add a link to sales channel for non storefront sales channel', async () => {
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

        expect(window.open).toBeCalledWith('http://shop/default-language', '_blank');

        wrapper.destroy();
    });

    it('prefers link to domain with actual admin language over others', async () => {
        window.open = jest.fn();

        const wrapper = await createWrapper([storefrontWithoutDefaultDomain]);
        await flushPromises();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toBeCalledWith('http://shop/admin-language', '_blank');

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

        expect(window.open).toBeCalledWith('http://shop/custom-language', '_blank');

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
});
