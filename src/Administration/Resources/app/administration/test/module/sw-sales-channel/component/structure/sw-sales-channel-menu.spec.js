import { mount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import 'src/module/sw-sales-channel/component/structure/sw-sales-channel-menu';
import 'src/app/component/base/sw-icon';
import 'src/app/component/structure/sw-admin-menu-item';

const defaultAdminLanguageId = '6a357734-afe4-4f17-a814-fb89ce9724fc';

const headlessSalesChannel = {
    id: '342112f7-ba2f-4c73-a63f-82918e67f953',
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
    id: '8106c8da-4528-406e-8b47-dcae65965f6b',
    active: true,
    domains: [{
        languageId: 'ab3e5a76-9e6a-493c-bc6c-117563976bcc',
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
    id: '0a660a4e-c1c8-4de7-a1cf-bd7a9c9886fa',
    active: true,
    domains: [{
        languageId: 'f084d9e0-cba4-4c42-bf99-3994e8fce125',
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
    id: '613cc4f6-1ace-4fbf-867a-e4b2ade87203',
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
    id: 'a9237944-c347-4583-88b9-6d00719baff6',
    active: false,
    domains: [{
        languageId: '14383ce0-d2b6-4c44-94a7-cf71b42fa35a',
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

        const wrapper = createWrapper(testSalesChannels);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelItems = wrapper.findAll('.sw-admin-menu__sales-channel-item');

        expect(salesChannelItems).toHaveLength(testSalesChannels.length);
    });

    it('It does not add a link to sales channel for non storefront sales channel', async () => {
        const wrapper = createWrapper([headlessSalesChannel]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);

        wrapper.destroy();
    });

    it('should use link to default language if exists', async () => {
        window.open = jest.fn();

        const wrapper = createWrapper([storeFrontWithStandardDomain]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toBeCalledWith('http://shop/default-language', '_blank');

        wrapper.destroy();
    });

    it('prefers link to domain with actual admin language over others', async () => {
        window.open = jest.fn();

        const wrapper = createWrapper([storefrontWithoutDefaultDomain]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toBeCalledWith('http://shop/admin-language', '_blank');

        wrapper.destroy();
    });

    it('takes first domain link if neither default language nor admin language exists', async () => {
        window.open = jest.fn();
        Shopware.State.get('session').languageId = Shopware.Utils.createId();

        const wrapper = createWrapper([storefrontWithoutDefaultDomain]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        const domainLinkButton = salesChannelMenuEntry.get('button.sw-sales-channel-menu-domain-link');

        await domainLinkButton.trigger('click');

        expect(window.open).toBeCalledWith('http://shop/custom-language', '_blank');

        wrapper.destroy();
    });

    it('does not pick a storefront domain if there is none', async () => {
        const wrapper = createWrapper([storefrontWithoutDomains]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);

        wrapper.destroy();
    });

    it('does not show a storefront domain if storefront is not active', async () => {
        const wrapper = createWrapper([inactiveStorefront]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const salesChannelMenuEntry = wrapper.find('.sw-admin-menu__sales-channel-item');
        expect(salesChannelMenuEntry.find('button.sw-sales-channel-menu-domain-link').exists()).toBe(false);

        wrapper.destroy();
    });
});
