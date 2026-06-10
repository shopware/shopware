import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

/**
 * @sw-package checkout
 */

async function createWrapper(
    privileges = [],
    editMode = false,
    { featureActive = false, routeName = 'sw.customer.detail.base', routerPush = jest.fn() } = {},
) {
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
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot></slot></div>',
                        props: [
                            'positionIdentifier',
                        ],
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot></slot></div>',
                        props: [
                            'route',
                            'title',
                            'hasError',
                        ],
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs"></div>',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
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
                    $route: {
                        name: routeName,
                        query: {
                            edit: editMode,
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: routerPush,
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
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
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
        Shopware.Store.get('error').resetApiErrors();
        wrapper = await createWrapper();
    });

    afterEach(() => {
        Shopware.Store.get('error').resetApiErrors();
        jest.restoreAllMocks();
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

    it('should set the initial limit on the addresses association criteria', async () => {
        await flushPromises();

        const criteria = wrapper.vm.defaultCriteria;
        const addressesAssociation = criteria.getAssociation('addresses');

        expect(addressesAssociation.limit).toBe(criteria.limit);
        expect(addressesAssociation.limit).toBe(25);
    });

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapperWithMeteorTabs = await createWrapper([], false, {
            featureActive: true,
            routeName: 'sw.customer.detail.addresses',
        });

        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-customer-detail-tabs');
        expect(tabs.props('defaultItem')).toBe('sw.customer.detail.addresses');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-customer.detail.tabGeneral',
                name: 'sw.customer.detail.base',
                hasError: false,
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
        expect(wrapperWithMeteorTabs.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapperWithMeteorTabs = await createWrapper([], true, {
            featureActive: true,
            routerPush,
        });
        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });
        const addressesTab = tabs.props('items').find((item) => item.name === 'sw.customer.detail.addresses');

        addressesTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.customer.detail.addresses',
            params: { id: 'cusotmerId' },
            query: { edit: true },
        });
    });

    it('should pass the general tab error state to meteor tabs', async () => {
        Shopware.Store.get('error').addApiError({
            expression: 'customer.test.email',
            error: new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        const wrapperWithMeteorTabs = await createWrapper([], false, {
            featureActive: true,
        });
        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')[0]).toEqual(
            expect.objectContaining({
                hasError: true,
            }),
        );
    });
});
