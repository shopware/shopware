import { mount } from '@vue/test-utils';
import swCustomerDetail from './index';

/**
 * @sw-package checkout
 */

function createTabs({ editMode = false, hasBaseError = false } = {}) {
    const routerPush = jest.fn(() => Promise.resolve());
    const routeQuery = { edit: editMode };
    const tabs = swCustomerDetail.computed.tabs.call({
        generalRoute: {
            name: 'sw.customer.detail.base',
            params: { id: 'customerId' },
            query: routeQuery,
        },
        addressesRoute: {
            name: 'sw.customer.detail.addresses',
            params: { id: 'customerId' },
            query: routeQuery,
        },
        ordersRoute: {
            name: 'sw.customer.detail.order',
            params: { id: 'customerId' },
            query: routeQuery,
        },
        swCustomerDetailBaseError: hasBaseError,
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

async function createWrapper(privileges = [], editMode = false) {
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
                    'mt-tabs': true,
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
                    feature: {
                        isActive: jest.fn(() => false),
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

    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs({ hasBaseError: true });

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-customer.detail.tabGeneral',
                name: 'sw.customer.detail.base',
                hasError: true,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-customer.detail.tabAddresses',
                name: 'sw.customer.detail.addresses',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-customer.detailBase.labelOrderCard',
                name: 'sw.customer.detail.order',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching customer route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs({ editMode: true });

        tabs[0].onClick();
        tabs[1].onClick();
        tabs[2].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, {
            name: 'sw.customer.detail.base',
            params: { id: 'customerId' },
            query: { edit: true },
        });
        expect(routerPush).toHaveBeenNthCalledWith(2, {
            name: 'sw.customer.detail.addresses',
            params: { id: 'customerId' },
            query: { edit: true },
        });
        expect(routerPush).toHaveBeenNthCalledWith(3, {
            name: 'sw.customer.detail.order',
            params: { id: 'customerId' },
            query: { edit: true },
        });
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

    it('should set the initial limit on the addresses association criteria', async () => {
        await flushPromises();

        const criteria = wrapper.vm.defaultCriteria;
        const addressesAssociation = criteria.getAssociation('addresses');

        expect(addressesAssociation.limit).toBe(criteria.limit);
        expect(addressesAssociation.limit).toBe(25);
    });
});
