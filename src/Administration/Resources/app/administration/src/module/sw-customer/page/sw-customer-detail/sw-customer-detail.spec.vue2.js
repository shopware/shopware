import { shallowMount } from '@vue/test-utils_v2';
import swCustomerDetail from 'src/module/sw-customer/page/sw-customer-detail';
import 'src/app/component/base/sw-button';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-button-process';

/**
 * @package checkout
 */

Shopware.Component.register('sw-customer-detail', swCustomerDetail);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-customer-detail'), {
        mocks: {
            $route: {
                query: {
                    edit: false,
                    page: 1,
                    limit: 25,
                },
            },
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        get: () => Promise.resolve({
                            id: 'test',
                            accountType: 'private',
                            company: 'Shopware AG',
                            requestedGroup: {
                                translated: {
                                    name: 'Test',
                                },
                            },
                        }),

                        searchIds: () => Promise.resolve({
                            total: 1,
                            data: ['1'],
                        }),
                    };
                },
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
            customerGroupRegistrationService: {
                accept: jest.fn().mockResolvedValue(true),
                decline: jest.fn().mockResolvedValue(true),
            },
            customerValidationService: {},
        },
        propsData: {
            customerEditMode: false,
            customerId: 'test',
            customer: {},
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-process': await Shopware.Component.build('sw-button-process'),
            'sw-language-switch': true,
            'sw-card-view': {
                template: '<div><slot></slot></div>',
            },
            'sw-card': {
                template: '<div><slot></slot></div>',
            },
            'sw-container': true,
            'sw-field': true,
            'sw-language-info': true,
            'sw-tabs': {
                template: '<div><slot name="content"></slot></div>',
            },
            'sw-tabs-item': true,
            'router-view': true,
            'sw-alert': {
                template: '<div><slot></slot></div>',
            },
            'sw-customer-card': {
                template: '<div></div>',
            },
            'sw-custom-field-set-renderer': await Shopware.Component.build('sw-custom-field-set-renderer'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
            'sw-skeleton': true,
            'sw-loader': true,
        },
    });
}

describe('module/sw-customer/page/sw-customer-detail', () => {
    let wrapper;

    beforeAll(() => {
        global.console.warn = jest.fn();
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep the customer\'s account type as private even when the company field is set', async () => {
        expect(wrapper.vm).toBeTruthy();

        expect(wrapper.vm.$data.customer.accountType).toBe('private');
        expect(wrapper.vm.$data.customer.company).toBe('Shopware AG');
    });

    it('should not be able to edit the customer', async () => {
        await wrapper.destroy();

        const wrapperWithPrivileges = await createWrapper();
        await wrapperWithPrivileges.setData({
            isLoading: false,
        });

        await wrapperWithPrivileges.vm.$nextTick();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();

        wrapperWithPrivileges.destroy();
    });

    it('should be able to edit the customer', async () => {
        await wrapper.destroy();

        const wrapperWithPrivileges = await createWrapper([
            'customer.editor',
        ]);
        await wrapperWithPrivileges.setData({
            isLoading: false,
        });
        await wrapperWithPrivileges.vm.$nextTick();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes().disabled).toBeFalsy();

        wrapperWithPrivileges.destroy();
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

    it('should have company validation when customer type is commercial', async () => {
        await wrapper.destroy();

        const wrapperWithPrivileges = await createWrapper([
            'customer.editor',
        ]);

        wrapperWithPrivileges.vm.createNotificationError = jest.fn();
        const notificationMock = wrapperWithPrivileges.vm.createNotificationError;
        wrapperWithPrivileges.vm.$route.query = { edit: true };
        await wrapperWithPrivileges.vm.$nextTick();

        await wrapperWithPrivileges.setData({
            customer: {
                id: '1',
                accountType: 'business',
                company: '',
            },
        });
        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__save-action');
        await saveButton.trigger('click');

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-customer.detail.messageSaveError',
        });

        wrapperWithPrivileges.vm.createNotificationError.mockRestore();
    });

    it('should get default salutation is value not specified', async () => {
        await flushPromises();

        expect(wrapper.vm.customer.salutationId).toBe('1');
    });
});
