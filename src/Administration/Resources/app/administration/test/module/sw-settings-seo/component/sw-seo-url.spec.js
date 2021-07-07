import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-settings-seo/component/sw-seo-url';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-seo-url'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div><slot name="toolbar"></slot></div>'
            },
            'sw-sales-channel-switch': true
        },
        provide: {
            repositoryFactory: {
                create: (entity) => ({
                    search: () => {
                        if (entity === 'sales_channel') {
                            return Promise.resolve(createEntityCollection([
                                {
                                    name: 'Storefront',
                                    translated: { name: 'Storefront' },
                                    id: '863137935ecf48999d69096de547b090'
                                },
                                {
                                    name: 'Headless',
                                    translated: { name: 'Headless' },
                                    id: '123456789'
                                }
                            ]));
                        }

                        return Promise.resolve([]);
                    },
                    create: () => ({}),
                    schema: {
                        entity: {}
                    }
                })
            }
        }
    });
}

describe('src/module/sw-settings-seo/component/sw-seo-url', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
        Shopware.State.commit('swSeoUrl/setCurrentSeoUrl', '');
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('sales channel switch should not be disabled', async () => {
        await wrapper.setData({
            showEmptySeoUrlError: false
        });

        const salesChannelSwitch = wrapper.find('sw-sales-channel-switch-stub');
        expect(salesChannelSwitch.attributes().disabled).toBeUndefined();
    });

    it('sales channel switch should be disabled', async () => {
        wrapper.vm.showEmptySeoUrlError = false;
        await wrapper.setProps({
            disabled: true
        });

        const salesChannelSwitch = wrapper.find('sw-sales-channel-switch-stub');
        expect(salesChannelSwitch.attributes().disabled).toBe('true');
    });

    it('should update currentSeoUrl when defaultSeoUrl empty', async () => {
        await wrapper.setProps({
            urls: [{
                id: 'c0221c1f712a4f369a79e924a10fa398',
                foreignKey: '4066b6039fcf41f089bdf859cc6ce662',
                languageId: '12345678',
                pathInfo: '/navigation/4066b6039fcf41f089bdf859cc6ce662',
                routeName: 'frontend.navigation.page',
                salesChannelId: '863137935ecf48999d69096de547b090',
                seoPathInfo: 'Computers/'
            }],
            salesChannelId: '863137935ecf48999d69096de547b090'
        });

        await wrapper.setData({
            showEmptySeoUrlError: false,
            currentSalesChannelId: '863137935ecf48999d69096de547b090'
        });

        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual({
            foreignKey: '4066b6039fcf41f089bdf859cc6ce662',
            isCanonical: true,
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            pathInfo: '/navigation/4066b6039fcf41f089bdf859cc6ce662',
            routeName: 'frontend.navigation.page',
            salesChannelId: '863137935ecf48999d69096de547b090',
            isModified: true
        });
    });

    it('should update currentSeoUrl when defaultSeoUrl empty and the salesChannel has no seo urls yet', async () => {
        await wrapper.setProps({
            urls: [{
                id: 'c0221c1f712a4f369a79e924a10fa398',
                foreignKey: '4066b6039fcf41f089bdf859cc6ce662',
                languageId: '12345678',
                pathInfo: '/navigation/4066b6039fcf41f089bdf859cc6ce662',
                routeName: 'frontend.navigation.page',
                salesChannelId: '4066b6039fcf41f089bdf859cc6ce662',
                seoPathInfo: 'Computers/'
            }],
            salesChannelId: '4066b6039fcf41f08rbdf859cc6ce662'
        });

        await wrapper.setData({
            showEmptySeoUrlError: false,
            currentSalesChannelId: '863137935ecf48999d69096de547b090'
        });

        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual({
            foreignKey: '4066b6039fcf41f089bdf859cc6ce662',
            isCanonical: true,
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            pathInfo: '/navigation/4066b6039fcf41f089bdf859cc6ce662',
            routeName: 'frontend.navigation.page',
            salesChannelId: '863137935ecf48999d69096de547b090',
            isModified: true
        });
    });

    it('should update currentSeoUrl when defaultSeoUrl not empty', async () => {
        await wrapper.setProps({
            urls: [{
                id: 'c0221c1f712a4f369a79e924a10fa398',
                foreignKey: '4066b6039fcf41f089bdf859cc6ce662',
                languageId: '12345678',
                pathInfo: '/navigation/4066b6039fcf41f089bdf859cc6ce662',
                routeName: 'frontend.navigation.page',
                salesChannelId: '863137935ecf48999d69096de547b090',
                seoPathInfo: 'Computers/'
            }, {
                id: '123456789',
                foreignKey: '12345678910111213',
                languageId: '1234567891011',
                pathInfo: '/navigation/123456789',
                routeName: 'frontend.product-detail.page',
                salesChannelId: null,
                seoPathInfo: 'Product-detail/'
            }],
            salesChannelId: '863137935ecf48999d69096de547b090'
        });

        await wrapper.setData({
            showEmptySeoUrlError: false,
            currentSalesChannelId: '863137935ecf48999d69096de547b090'
        });

        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({
            id: '123456789',
            foreignKey: '12345678910111213',
            languageId: '1234567891011',
            pathInfo: '/navigation/123456789',
            routeName: 'frontend.product-detail.page',
            salesChannelId: null,
            seoPathInfo: 'Product-detail/'
        });
        expect(wrapper.vm.currentSeoUrl).toEqual({
            foreignKey: '12345678910111213',
            isCanonical: true,
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            pathInfo: '/navigation/123456789',
            routeName: 'frontend.product-detail.page',
            salesChannelId: '863137935ecf48999d69096de547b090',
            isModified: true
        });
    });
});
