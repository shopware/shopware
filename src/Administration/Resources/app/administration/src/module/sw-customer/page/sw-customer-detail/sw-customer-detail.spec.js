import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const defaultCustomer = {
    id: 'test',
    accountType: 'private',
    company: 'Shopware AG',
    requestedGroup: {
        translated: {
            name: 'Test',
        },
    },
};

async function createWrapper(privileges = [], editMode = false, customerResponse = defaultCustomer) {
    return mount(
        await wrapTestComponent('sw-customer-detail', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
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
                    'sw-customer-card': {
                        template: '<div></div>',
                    },
                    'sw-custom-field-set-renderer': await wrapTestComponent('sw-custom-field-set-renderer'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-skeleton': true,
                    'sw-loader': true,
                },
                mocks: {
                    $route: {
                        name: 'sw.cusomter.detail',
                        query: {
                            edit: editMode,
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: jest.fn(),
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(customerResponse),

                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['1'],
                                    }),
                            };
                        },
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customerGroupRegistrationService: {
                        accept: jest.fn().mockResolvedValue(true),
                        decline: jest.fn().mockResolvedValue(true),
                    },
                    customerValidationService: {},
                },
            },

            props: {
                customerId: 'cusotmerId',
            },
        },
    );
}

describe('module/sw-customer/page/sw-customer-detail', () => {
    let wrapper;

    beforeAll(() => {
        global.console.warn = jest.fn();
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it("should keep the customer's account type as private even when the company field is set", async () => {
        expect(wrapper.vm).toBeTruthy();

        expect(wrapper.vm.$data.customer.accountType).toBe('private');
        expect(wrapper.vm.$data.customer.company).toBe('Shopware AG');
    });

    it('should not be able to edit the customer', async () => {
        const wrapperWithPrivileges = await createWrapper();

        await flushPromises();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBe('');

        wrapperWithPrivileges.unmount();
    });

    it('should be able to edit the customer', async () => {
        const wrapperWithPrivileges = await createWrapper([
            'customer.editor',
        ]);

        await flushPromises();

        const saveButton = wrapperWithPrivileges.find('.sw-customer-detail__open-edit-mode-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should accept customer registration button called', async () => {
        await wrapper.setData({
            customer: {
                active: true,
            },
        });

        await flushPromises();

        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:last-child');
        expect(button.attributes().disabled).toBeFalsy();
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.accept).toHaveBeenCalled();
    });

    it('should decline customer registration button called', async () => {
        await wrapper.setData({
            customer: {
                active: true,
            },
        });

        await flushPromises();

        expect(wrapper.vm.customerGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.customerGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-customer-detail__customer-registration-alert button:first-child');
        expect(button.attributes().disabled).toBeFalsy();
        await button.trigger('click');

        expect(wrapper.vm.customerGroupRegistrationService.decline).toHaveBeenCalled();
    });

    it('should have company validation when customer type is commercial', async () => {
        const wrapperWithPrivileges = await createWrapper(
            [
                'customer.editor',
            ],
            true,
        );

        await flushPromises();

        wrapperWithPrivileges.vm.createNotificationError = jest.fn();
        const notificationMock = wrapperWithPrivileges.vm.createNotificationError;

        await wrapperWithPrivileges.setData({
            customer: {
                id: '1',
                accountType: 'business',
                company: '',
            },
        });

        const saveButton = wrapperWithPrivileges.findComponent('.sw-customer-detail__save-action');
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

    it('should redirect to the customer listing when the customer does not exist', async () => {
        let resolveCustomer = () => {};
        const customerPromise = new Promise((resolve) => {
            resolveCustomer = resolve;
        });
        const wrapperWithMissingCustomer = await createWrapper([], false, customerPromise);
        wrapperWithMissingCustomer.vm.createNotificationError = jest.fn();

        resolveCustomer(null);

        await flushPromises();

        expect(wrapperWithMissingCustomer.vm.customer).toBeNull();
        expect(wrapperWithMissingCustomer.vm.isLoading).toBe(false);
        expect(wrapperWithMissingCustomer.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-customer.detail.messageCustomerNotFound',
        });
        expect(wrapperWithMissingCustomer.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.customer.index' });
    });

    it('should show a notification when the customer cannot be loaded', async () => {
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        const wrapperWithLoadingError = await createWrapper([], false, Promise.reject(new Error('Could not load customer')));

        await flushPromises();

        expect(wrapperWithLoadingError.vm.customer).toBeNull();
        expect(wrapperWithLoadingError.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });
        expect(wrapperWithLoadingError.vm.$router.push).not.toHaveBeenCalled();

        notificationSpy.mockRestore();
    });

    it('should set the initial limit on the addresses association criteria', async () => {
        await flushPromises();

        const criteria = wrapper.vm.defaultCriteria;
        const addressesAssociation = criteria.getAssociation('addresses');

        expect(addressesAssociation.limit).toBe(criteria.limit);
        expect(addressesAssociation.limit).toBe(25);
    });
});
