/**
 * @package services-settings
 */

import { mount } from '@vue/test-utils';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-customer-group-detail', {
            sync: true,
        }),
        {
            props: {
                customerGroupId: '1',
            },
            global: {
                mocks: {
                    $route: { query: '' },
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
                    'sw-text-field': {
                        props: [
                            'label',
                            'value',
                            'disabled',
                            'copyable',
                        ],
                        template: `
                        <div class="sw-text-field-stub"
                             :label="label"
                            :value="value"
                            :disabled="disabled"
                            :copyable="copyable"
                        >
                          <slot></slot>
                        </div>`,
                    },
                    'sw-textarea-field': true,
                    'sw-text-editor': true,
                    'sw-language-info': true,
                    'sw-button': true,
                    'sw-button-process': true,
                    'sw-switch-field': true,
                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-label': true,
                    'sw-icon': true,
                    'sw-loader': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-highlight-text': true,
                    'sw-popover': {
                        props: ['popoverClass'],
                        template: `
                    <div class="sw-popover" :class="popoverClass">
                        <slot></slot>
                    </div>`,
                    },
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-custom-field-set-renderer': true,
                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-field-error': true,
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
                                    registrationSalesChannels: new EntityCollection(
                                        '/customer-group/1/registration-sales-channels',
                                        'sales_channel',
                                        Context.api,
                                        null,
                                        [
                                            {
                                                id: '123',
                                            },
                                        ],
                                    ),
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
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail', () => {
    describe('should not able to save without edit permission', () => {
        let wrapper;

        beforeEach(async () => {
            wrapper = await createWrapper();
            await wrapper.vm.$nextTick();
        });

        [
            {
                name: 'save button',
                selector: '.sw-settings-customer-group-detail__save',
            },
            {
                name: 'name field ',
                selector: '.sw-settings-customer-group-detail__name',
            },
            {
                name: 'gross radio group',
                selector: 'sw-boolean-radio-group-stub',
            },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]',
            },
            {
                name: 'form title field',
                selector: '.sw-text-field-stub[label="sw-settings-customer-group.registration.title"]',
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
            {
                name: 'sales channel multiple select',
                selector: '.sw-entity-multi-select',
            },
        ].forEach(({ name, selector }) => {
            it(`${name} should be disabled`, async () => {
                await flushPromises();
                const element = wrapper.findComponent(selector);

                // Condition for different types of components
                if (element.attributes().hasOwnProperty('disabled')) {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(element.attributes().disabled).toBeTruthy();
                } else {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(element.props().disabled).toBeTruthy();
                }
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
            {
                name: 'save button',
                selector: '.sw-settings-customer-group-detail__save',
            },
            {
                name: 'name field ',
                selector: '.sw-settings-customer-group-detail__name',
            },
            {
                name: 'gross radio group',
                selector: 'sw-boolean-radio-group-stub',
            },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]',
            },
            {
                name: 'form title field',
                selector: '.sw-text-field-stub[label="sw-settings-customer-group.registration.title"]',
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
            {
                name: 'sales channel multiple select',
                selector: '.sw-entity-multi-select',
            },
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
});
