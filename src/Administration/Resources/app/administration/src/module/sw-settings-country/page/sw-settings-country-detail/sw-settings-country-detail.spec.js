/**
 * @package buyers-experience
 */
import { config, createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsCountryDetail from 'src/module/sw-settings-country/page/sw-settings-country-detail';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

import VueRouter from 'vue-router';

Shopware.Component.register('sw-settings-country-detail', swSettingsCountryDetail);

const routes = [
    {
        name: 'sw.settings.country.detail',
        path: '/sw/settings/country/detail/the-id',
        children: [
            {
                name: 'sw.settings.country.detail.general',
                path: '/sw/settings/country/detail/the-id/general',
            }, {
                name: 'sw.settings.country.detail.state',
                path: '/sw/settings/country/detail/the-id/state',
            },
            {
                name: 'sw.settings.country.detail.address-handling',
                path: '/sw/settings/country/detail/the-id/address-handling',
            },
        ],
    },
];

const router = new VueRouter({
    routes,
});

async function createWrapper(privileges = []) {
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-country-detail'), {
        localVue,
        router,
        mocks: {
            $tc: key => key,
            $device: {
                removeResizeListener: () => {},
                getSystemKey: () => {},
                onResize: () => {},
            },
        },

        provide: {
            repositoryFactory: {
                create: (entity) => ({
                    get: () => {
                        if (entity === 'country') {
                            return Promise.resolve({
                                isNew: () => false,
                                active: true,
                                apiAlias: null,
                                createdAt: '2020-08-12T02:49:39.974+00:00',
                                customFields: null,
                                customerAddresses: [],
                                displayStateInRegistration: false,
                                forceStateInRegistration: false,
                                id: '44de136acf314e7184401d36406c1e90',
                                iso: 'AL',
                                iso3: 'ALB',
                                name: 'Albania',
                                orderAddresses: [],
                                position: 10,
                                salesChannelDefaultAssignments: [],
                                salesChannels: [],
                                shippingAvailable: true,
                                states: [],
                                taxFree: false,
                                taxRules: [],
                                translated: {},
                                translations: [],
                                updatedAt: '2020-08-16T06:57:40.559+00:00',
                                vatIdRequired: false,
                            });
                        }

                        return Promise.resolve({
                            systemCurrency: {
                                symbol: '€',
                            },
                        });
                    },
                    search: () => {
                        return Promise.resolve({
                            first: () => ({}),
                            length: 0,
                        });
                    },
                    create: () => {
                        return {};
                    },
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([]),
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
            'sw-card-view': await Shopware.Component.build('sw-card-view'),
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-language-switch': true,
            'sw-language-info': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-field': true,
            'sw-switch-field': true,
            'sw-icon': true,
            'sw-simple-search-field': true,
            'sw-context-menu-item': true,
            'sw-number-field': true,
            'sw-one-to-many-grid': true,
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'router-link': true,
            'router-view': true,
            'sw-skeleton': true,
            'sw-settings-country-sidebar': true,
            'sw-error-summary': true,
        },
    });
}

describe('module/sw-settings-country/page/sw-settings-country-detail', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be render tab', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.vm.$nextTick();
        const generalTab = wrapper.find('.sw-settings-country__setting-tab');
        const stateTab = wrapper.find('.sw-settings-country__state-tab');

        expect(generalTab.exists()).toBeTruthy();
        expect(stateTab.exists()).toBeTruthy();
    });

    it('should be able to save the country', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action',
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to save the country', async () => {
        const wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action',
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
    });
});
