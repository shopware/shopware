import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-detail';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-customer-detail'), {
        mocks: {
            $route: {
                query: {
                    edit: false,
                    page: 1,
                    limit: 25
                }
            }
        },
        provide: { repositoryFactory: {
            create: () => {
                return {
                    get: () => Promise.resolve({
                        id: 'test',
                        requestedGroup: {
                            translated: {
                                name: 'Test'
                            }
                        }
                    })
                };
            }
        },
        acl: {
            can: (identifier) => {
                if (!identifier) { return true; }

                return privileges.includes(identifier);
            }
        },
        customerGroupRegistrationService: {
            accept: jest.fn().mockResolvedValue(true),
            decline: jest.fn().mockResolvedValue(true)
        },
        customerValidationService: {},
        systemConfigApiService: {
            getValues: () => Promise.resolve([])
        } },
        propsData: {
            customerEditMode: false,
            customerId: 'test',
            customer: {}
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-button-process': true,
            'sw-language-switch': true,
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-card': {
                template: '<div><slot></slot></div>'
            },
            'sw-container': true,
            'sw-field': true,
            'sw-language-info': true,
            'sw-tabs': {
                template: '<div><slot name="content"></slot></div>'
            },
            'sw-tabs-item': true,
            'router-view': true,
            'sw-alert': {
                template: '<div><slot></slot></div>'
            },
            'sw-customer-card': {
                template: '<div></div>'
            },
            'sw-custom-field-set-renderer': Shopware.Component.build('sw-custom-field-set-renderer'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper')
        }
    });
}

describe('module/sw-customer/page/sw-customer-detail', () => {
    let wrapper;

    beforeAll(() => {
        global.console.warn = jest.fn();
    });

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to edit the customer', async () => {
        const wrapperWithPrivileges = createWrapper();
        await wrapperWithPrivileges.setData({
            isLoading: false
        });

        await wrapperWithPrivileges.vm.$nextTick();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit the customer', async () => {
        const wrapperWithPrivileges = createWrapper([
            'customer.editor'
        ]);
        await wrapperWithPrivileges.setData({
            isLoading: false
        });
        await wrapperWithPrivileges.vm.$nextTick();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should accept customer registration button called', async () => {
        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:last-child');
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.accept).toHaveBeenCalled();
    });

    it('should decline customer registration button called', async () => {
        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:first-child');
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.decline).toHaveBeenCalled();
    });
});
