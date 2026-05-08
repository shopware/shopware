import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(privileges = [], editMode = false, routeName = 'sw.customer.detail.base') {
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
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        props: [
                            'items',
                            'defaultItem',
                            'positionIdentifier',
                        ],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                    },
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
                        name: routeName,
                        params: {
                            id: 'customerId',
                        },
                        query: {
                            edit: editMode,
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () =>
                                    Promise.resolve({
                                        id: 'test',
                                        accountType: 'private',
                                        company: 'Shopware AG',
                                        requestedGroup: {
                                            translated: {
                                                name: 'Test',
                                            },
                                        },
                                    }),

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
                customerId: 'customerId',
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
        global.activeFeatureFlags = [''];
        wrapper = await createWrapper();
    });

    it('should render legacy tabs when the major feature flag is inactive', async () => {
        expect(wrapper.find('sw-tabs-stub').exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render meteor route tabs when the major feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const activeWrapper = await createWrapper([], false, 'sw.customer.detail.addresses');

        const mtTabs = activeWrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-customer.detail.tabGeneral',
                name: 'general',
                hasError: activeWrapper.vm.swCustomerDetailBaseError,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-customer.detail.tabAddresses',
                name: 'addresses',
                onClick: expect.any(Function),
            },
            {
                label: 'sw-customer.detailBase.labelOrderCard',
                name: 'order',
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('addresses');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-customer-detail-tabs');
        expect(activeWrapper.find('sw-tabs-stub').exists()).toBe(false);

        mtTabs.props('items')[0].onClick();
        expect(activeWrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.customer.detail.base',
            params: { id: 'customerId' },
            query: { edit: false },
        });

        mtTabs.props('items')[1].onClick();
        expect(activeWrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.customer.detail.addresses',
            params: { id: 'customerId' },
            query: { edit: false },
        });

        mtTabs.props('items')[2].onClick();
        expect(activeWrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.customer.detail.order',
            params: { id: 'customerId' },
            query: { edit: false },
        });
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
});
