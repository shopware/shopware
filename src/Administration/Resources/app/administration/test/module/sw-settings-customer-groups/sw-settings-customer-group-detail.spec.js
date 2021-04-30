import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-customer-group-detail'), {
        localVue,
        mocks: {
            $route: { query: '' }
        },

        propsData: {
            customerGroupId: '1'
        },

        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-field': true,
            'sw-boolean-radio-group': true,
            'sw-text-field': true,
            'sw-text-editor': true,
            'sw-language-info': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-switch-field': true,
            'sw-entity-multi-select': true,
            'sw-custom-field-set-renderer': true
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {
                            id: '',
                            name: '',
                            displayGross: false,
                            isNew: () => true
                        };
                    },

                    get: () => {
                        return Promise.resolve({
                            id: '1',
                            name: 'Net price customer group',
                            displayGross: false,
                            registrationActive: true,
                            isNew: () => false
                        });
                    },

                    search: () => {
                        return Promise.resolve([
                            {
                                id: '123',
                                seoPathInfo: 'Hello-world',
                                salesChannel: {
                                    translated: {
                                        name: 'Storefront'
                                    },
                                    domains: [
                                        {
                                            languageId: '1234',
                                            url: 'http://shopware.test'
                                        }
                                    ]
                                },
                                languageId: '1234'
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        }
    });
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail', () => {
    describe('should not able to save without edit permission', () => {
        let wrapper;

        beforeEach(() => {
            wrapper = createWrapper();
            wrapper.vm.$nextTick();
        });

        [
            { name: 'save button', selector: '.sw-settings-customer-group-detail__save' },
            { name: 'name field ', selector: '.sw-settings-customer-group-detail__name' },
            { name: 'gross radio group', selector: 'sw-boolean-radio-group-stub' },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]'
            },
            {
                name: 'form title field',
                selector: 'sw-field-stub[label="sw-settings-customer-group.registration.title"]'
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.registration.onlyCompaniesCanRegister"]'
            },
            {
                name: 'seo meta field',
                selector: 'sw-field-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]'
            },
            { name: 'sales channel multiple select', selector: 'sw-entity-multi-select-stub' }
        ].forEach(({ name, selector }) => {
            it(`${name} should be disabled`, async () => {
                const element = wrapper.find(selector);
                return expect(element.attributes().disabled).toBeTruthy();
            });
        });

        it('should show warning tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'sw-privileges.tooltip.warning',
                disabled: false,
                showOnDisabledElements: true
            });
        });
    });

    describe('should able to save with edit permission', () => {
        let wrapper;

        beforeEach(() => {
            wrapper = createWrapper(['customer_groups.editor']);
            wrapper.vm.$nextTick();
        });

        [
            { name: 'save button', selector: '.sw-settings-customer-group-detail__save' },
            { name: 'name field ', selector: '.sw-settings-customer-group-detail__name' },
            { name: 'gross radio group', selector: 'sw-boolean-radio-group-stub' },
            {
                name: 'registration form switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.detail.registrationForm"]'
            },
            {
                name: 'form title field',
                selector: 'sw-field-stub[label="sw-settings-customer-group.registration.title"]'
            },
            { name: 'form editor', selector: 'sw-text-editor-stub' },
            {
                name: 'only company switch',
                selector: 'sw-switch-field-stub[label="sw-settings-customer-group.registration.onlyCompaniesCanRegister"]'
            },
            {
                name: 'seo meta field',
                selector: 'sw-field-stub[label="sw-settings-customer-group.registration.seoMetaDescription"]'
            },
            { name: 'sales channel multiple select', selector: 'sw-entity-multi-select-stub' }
        ].forEach(({ name, selector }) => {
            it(`${name} should be enabled`, async () => {
                const element = wrapper.find(selector);
                return expect(element.attributes().disabled).toBeFalsy();
            });
        });

        it('should show save shortcut tooltip', async () => {
            expect(wrapper.vm.tooltipSave).toStrictEqual({
                message: 'CTRL + S',
                appearance: 'light'
            });
        });
    });
});
