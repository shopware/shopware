import { shallowMount, createLocalVue } from '@vue/test-utils';
import swSettingsCustomerGroupDetail from 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/utils/sw-popover';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-settings-customer-group-detail', swSettingsCustomerGroupDetail);

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-settings-customer-group-detail'), {
        localVue,
        mocks: {
            $route: { query: '' },
        },

        propsData: {
            customerGroupId: '1',
        },

        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>',
            },
            'sw-card': {
                template: '<div><slot></slot></div>',
            },
            'sw-container': {
                template: '<div><slot></slot></div>',
            },
            'sw-boolean-radio-group': true,
            'sw-text-field': true,
            'sw-textarea-field': true,
            'sw-text-editor': true,
            'sw-language-info': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': await Shopware.Component.build('sw-entity-multi-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-block-field': true,
            'sw-label': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-highlight-text': true,
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-custom-field-set-renderer': true,
            'sw-skeleton': true,
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            id: '',
                            name: '',
                            displayGross: false,
                            isNew: () => true,
                        };
                    },

                    get: () => {
                        return Promise.resolve({
                            id: '1',
                            name: 'Net price customer group',
                            displayGross: false,
                            registrationActive: true,
                            registrationSalesChannels: new EntityCollection('/customer-group/1/registration-sales-channels', 'sales_channel', Context.api, null, [
                                {
                                    id: '123',
                                },
                            ]),
                            isNew: () => false,
                        });
                    },

                    search: () => {
                        return Promise.resolve([
                            {
                                id: '123',
                                seoPathInfo: 'Hello-world',
                                salesChannel: {
                                    translated: {
                                        name: 'Storefront',
                                    },
                                    domains: [
                                        {
                                            languageId: '1234',
                                            url: 'http://shopware.test',
                                        },
                                    ],
                                },
                                languageId: '1234',
                            },
                        ]);
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
    });
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail', () => {
    describe('should not able to save without edit permission', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await wrapper.vm.$nextTick();
        });

        [
            { name: 'save button', selector: '.sw-settings-customer-group-detail__save' },
            { name: 'name field ', selector: '.sw-settings-customer-group-detail__name' },
            { name: 'gross radio group', selector: 'sw-boolean-radio-group-stub' },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]',
            },
            {
                name: 'form title field',
                selector: 'sw-text-field-stub[label="sw-settings-customer-group.registration.title"]',
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.registration.onlyCompaniesCanRegister"]',
            },
            {
                name: 'seo meta field',
                selector: 'sw-textarea-field-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]',
            },
            { name: 'sales channel multiple select', selector: '.sw-entity-multi-select' },
        ].forEach(({ name, selector }) => {
            it(`${name} should be disabled`, async () => {
                const element = wrapper.find(selector);
                expect(element.attributes().disabled).toBeTruthy();
            });
        });

        it('should show warning tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'sw-privileges.tooltip.warning',
                disabled: false,
                showOnDisabledElements: true,
            });
        });
    });

    describe('should able to save with edit permission', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper(['customer_groups.editor']);
            await wrapper.vm.$nextTick();
        });

        [
            { name: 'save button', selector: '.sw-settings-customer-group-detail__save' },
            { name: 'name field ', selector: '.sw-settings-customer-group-detail__name' },
            { name: 'gross radio group', selector: 'sw-boolean-radio-group-stub' },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]',
            },
            {
                name: 'form title field',
                selector: 'sw-text-field-stub[label="sw-settings-customer-group.registration.title"]',
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.registration.onlyCompaniesCanRegister"]',
            },
            {
                name: 'seo meta field',
                selector: 'sw-textarea-field-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]',
            },
            { name: 'sales channel multiple select', selector: '.sw-entity-multi-select' },
        ].forEach(({ name, selector }) => {
            it(`${name} should be enabled`, async () => {
                const element = wrapper.find(selector);
                expect(element.attributes().disabled).toBeFalsy();
            });
        });

        it('should show save shortcut tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'CTRL + S',
                appearance: 'light',
            });
        });
    });

    it('should be removed SEO URL if available sales channel is removed', async () => {
        const wrapper = await createWrapper(['customer_groups.editor']);

        await flushPromises();

        expect(wrapper.vm.seoUrls).toEqual([{
            id: '123',
            seoPathInfo: 'Hello-world',
            salesChannel: {
                translated: {
                    name: 'Storefront',
                },
                domains: [
                    {
                        languageId: '1234',
                        url: 'http://shopware.test',
                    },
                ],
            },
            languageId: '1234',
        }]);
        expect(wrapper.find('sw-text-field-stub[label="sw-settings-customer-group.registration.seoUrlLabel"]').attributes()).toEqual({
            label: 'sw-settings-customer-group.registration.seoUrlLabel',
            copyable: 'true',
            disabled: 'true',
            value: 'http://shopware.test/Hello-world',
        });

        const salesChannelSelect = wrapper.find('.sw-entity-multi-select');
        await salesChannelSelect.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const selectStorefront = wrapper.find('.sw-select-option--0');
        await selectStorefront.trigger('click');

        expect(wrapper.vm.seoUrls).toEqual([]);
        expect(wrapper.find('sw-text-field-stub[label="Storefront"]').exists()).toBe(false);
    });
});
