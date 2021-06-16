import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/page/sw-settings-country-detail';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

function createWrapper(privileges = [], isActiveFeature) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-detail'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
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
                                vatIdRequired: false
                            });
                        }

                        return Promise.resolve({
                            systemCurrency: {
                                symbol: '€'
                            }
                        });
                    },
                    search: () => {
                        return Promise.resolve({
                            userConfigs: {
                                first: () => ({})
                            }
                        });
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => isActiveFeature
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
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
                `
            },
            'sw-card-view': Shopware.Component.build('sw-card-view'),
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-container': Shopware.Component.build('sw-container'),
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
            'sw-one-to-many-grid': {
                props: ['columns', 'allowDelete'],
                template: `
                    <div>
                        <template v-for="item in columns">
                            <slot name="more-actions" v-bind="{ item }"></slot>
                            <slot name="delete-action" :item="item">
                                <sw-context-menu-item
                                    class="sw-one-to-many-grid__delete-action"
                                    variant="danger"
                                    :disabled="!allowDelete"
                                    @click="deleteItem(item.id)">
                                    {{ $tc('global.default.delete') }}
                                </sw-context-menu-item>
                            </slot>
                        </template>
                    </div>
                `
            },
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'router-link': true,
            'router-view': true
        }
    });
}

describe('module/sw-settings-country/page/sw-settings-country-detail', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save the country', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], false);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action'
        );
        const countryNameField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelName"]'
        );
        const countryPositionField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelPosition"]'
        );
        const countryIsoField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelIso"]'
        );
        const countryIso3Field = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelIso3"]'
        );
        const countryActiveField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelActive"]'
        );
        const countryShippingAvailableField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelShippingAvailable"]'
        );
        const countryTaxFreeField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelTaxFree"]'
        );
        const countryCompaniesTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCompanyTaxFree"]'
        );
        const countryCheckVatIdFormatField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCheckVatIdFormat"]'
        );
        const countryForceStateInRegistrationField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(countryNameField.attributes().disabled).toBeUndefined();
        expect(countryPositionField.attributes().disabled).toBeUndefined();
        expect(countryIsoField.attributes().disabled).toBeUndefined();
        expect(countryIso3Field.attributes().disabled).toBeUndefined();
        expect(countryActiveField.attributes().disabled).toBeUndefined();
        expect(countryShippingAvailableField.attributes().disabled).toBeUndefined();
        expect(countryTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeUndefined();
        expect(countryForceStateInRegistrationField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the country', async () => {
        const wrapper = createWrapper([], false);

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action'
        );
        const countryNameField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelName"]'
        );
        const countryPositionField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelPosition"]'
        );
        const countryIsoField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelIso"]'
        );
        const countryIso3Field = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelIso3"]'
        );
        const countryActiveField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelActive"]'
        );
        const countryShippingAvailableField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelShippingAvailable"]'
        );
        const countryTaxFreeField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelTaxFree"]'
        );
        const countryCompaniesTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCompanyTaxFree"]'
        );
        const countryCheckVatIdFormatField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCheckVatIdFormat"]'
        );
        const countryForceStateInRegistrationField = wrapper.find(
            'sw-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(countryNameField.attributes().disabled).toBeTruthy();
        expect(countryPositionField.attributes().disabled).toBeTruthy();
        expect(countryIsoField.attributes().disabled).toBeTruthy();
        expect(countryIso3Field.attributes().disabled).toBeTruthy();
        expect(countryActiveField.attributes().disabled).toBeTruthy();
        expect(countryShippingAvailableField.attributes().disabled).toBeTruthy();
        expect(countryTaxFreeField.attributes().disabled).toBeTruthy();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeTruthy();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeTruthy();
        expect(countryForceStateInRegistrationField.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], false);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-detail__add-country-state-button');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country state', async () => {
        const wrapper = createWrapper([], false);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-country-detail__add-country-state-button');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], false);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-country-detail__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a country state', async () => {
        const wrapper = createWrapper([], false);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-country-detail__edit-country-state-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a country state', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], false);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a country state', async () => {
        const wrapper = createWrapper([], false);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-one-to-many-grid__delete-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be render tab', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], true);

        await wrapper.vm.$nextTick();
        const generalTab = wrapper.find('.sw-settings-country__setting-tab');
        const stateTab = wrapper.find('.sw-settings-country__state-tab');

        expect(generalTab.exists()).toBeTruthy();
        expect(stateTab.exists()).toBeTruthy();
    });

    it('should be able to save the country', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], true);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action'
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to save the country', async () => {
        const wrapper = createWrapper([], true);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-country-detail__save-action'
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
    });
});
