import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper(privileges = [], editMode = false, { route, routerPush } = {}) {
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
                    'mt-tabs': {
                        template: '<div class="mt-tabs-stub"></div>',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                required: false,
                                default: null,
                            },
                            routeTabs: {
                                type: Boolean,
                                required: false,
                                default: false,
                            },
                        },
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
                    $route: route ?? {
                        name: 'sw.customer.detail.base',
                        params: { id: 'cusotmerId' },
                        query: {
                            edit: editMode,
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: routerPush ?? jest.fn(),
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
        global.activeFeatureFlags = [];

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

    it('should render route-backed Meteor tabs when the feature is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const routerPush = jest.fn();
        const wrapperWithMeteorTabs = await createWrapper([], true, {
            routerPush,
            route: {
                name: 'sw.customer.detail.base',
                params: { id: 'cusotmerId' },
                query: { edit: true },
            },
        });

        const tabs = wrapperWithMeteorTabs.getComponent('.mt-tabs-stub');
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-customer-detail-tabs');
        expect(tabs.props('defaultItem')).toBe('sw.customer.detail.base');
        expect(tabs.props('routeTabs')).toBe(true);
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-customer.detail.tabGeneral',
                name: 'sw.customer.detail.base',
            }),
            expect.objectContaining({
                label: 'sw-customer.detail.tabAddresses',
                name: 'sw.customer.detail.addresses',
            }),
            expect.objectContaining({
                label: 'sw-customer.detailBase.labelOrderCard',
                name: 'sw.customer.detail.order',
            }),
        ]);

        items[1].onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.customer.detail.addresses',
            params: { id: 'cusotmerId' },
            query: { edit: true },
        });
    });
});
