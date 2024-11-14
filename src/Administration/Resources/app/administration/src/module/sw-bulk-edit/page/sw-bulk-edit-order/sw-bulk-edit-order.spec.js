/**
 * @package services-settings
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import Criteria from 'src/core/data/criteria.data';

const selectedOrderId = Shopware.Utils.createId();

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order', () => {
    let wrapper;
    let routes;

    async function createWrapper(isResponseError = false) {
        // delete global $router and $routes mocks
        delete config.global.mocks.$router;
        delete config.global.mocks.$route;

        const router = createRouter({
            history: createWebHashHistory(),
            routes,
        });
        router.push('/');
        await router.isReady();

        return mount(await wrapTestComponent('sw-bulk-edit-order', { sync: true }), {
            global: {
                plugins: [
                    router,
                ],
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-loader': true,
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-bulk-edit-custom-fields': await wrapTestComponent('sw-bulk-edit-custom-fields'),
                    'sw-bulk-edit-change-type-field-renderer': await wrapTestComponent(
                        'sw-bulk-edit-change-type-field-renderer',
                        { sync: true },
                    ),
                    'sw-bulk-edit-form-field-renderer': await wrapTestComponent('sw-bulk-edit-form-field-renderer'),
                    'sw-bulk-edit-change-type': await wrapTestComponent('sw-bulk-edit-change-type'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-empty-state': await wrapTestComponent('sw-empty-state'),
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-bulk-edit-order-documents': await wrapTestComponent('sw-bulk-edit-order-documents'),
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-number-field': await wrapTestComponent('sw-number-field'),
                    'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-custom-field-set-renderer': true,
                    'sw-text-editor-toolbar': true,
                    'sw-app-actions': true,
                    'sw-search-bar': true,
                    'sw-datepicker': true,
                    'sw-text-editor': true,
                    'sw-language-switch': true,
                    'sw-notification-center': true,
                    'sw-help-center': true,
                    'sw-icon': true,
                    'sw-help-text': true,
                    'sw-alert': true,
                    'sw-label': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-ignore-class': true,
                    'sw-extension-component-section': true,
                    'sw-bulk-edit-order-documents-generate-invoice': true,
                    'sw-bulk-edit-order-documents-generate-cancellation-invoice': true,
                    'sw-bulk-edit-order-documents-generate-delivery-note': true,
                    'sw-bulk-edit-order-documents-generate-credit-note': true,
                    'sw-bulk-edit-order-documents-download-documents': true,
                    'sw-entity-tag-select': true,
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-error-summary': true,
                    'sw-app-topbar-button': true,
                    'sw-help-center-v2': true,
                    'mt-button': true,
                    'mt-checkbox': true,
                    'sw-context-button': true,
                    'sw-inheritance-switch': true,
                    'mt-card': true,
                    'sw-ai-copilot-badge': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-highlight-text': true,
                    'mt-tabs': true,
                    'mt-switch': true,
                    'mt-text-field': true,
                    'sw-field-copyable': true,
                    'sw-media-collapse': true,
                },
                provide: {
                    validationService: {},
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'custom_field_set') {
                                return {
                                    search: () =>
                                        Promise.resolve(
                                            createEntityCollection([
                                                {
                                                    id: 'field-set-id-1',
                                                    name: 'example',
                                                    customFields: [
                                                        {
                                                            name: 'customFieldName',
                                                            type: 'text',
                                                            config: {
                                                                label: 'configFieldLabel',
                                                            },
                                                        },
                                                    ],
                                                },
                                            ]),
                                        ),
                                    get: () => Promise.resolve({ id: '' }),
                                };
                            }

                            if (entity === 'state_machine_state') {
                                return {
                                    searchIds: jest.fn(),
                                };
                            }

                            return {
                                create: () => {
                                    if (entity === 'custom_field_set') {
                                        return {
                                            search: () =>
                                                Promise.resolve([
                                                    {
                                                        id: 'field-set-id-1',
                                                    },
                                                ]),
                                            get: () => Promise.resolve({ id: '' }),
                                        };
                                    }

                                    return {
                                        id: '1a2b3c',
                                        name: 'Test order',
                                    };
                                },
                                search: () =>
                                    Promise.resolve([
                                        {
                                            id: 1,
                                            name: 'Invoice',
                                        },
                                        {
                                            id: 2,
                                            name: 'Credit note',
                                        },
                                    ]),
                                get: () =>
                                    Promise.resolve({
                                        id: 1,
                                        name: 'Order',
                                    }),
                                searchIds: () =>
                                    Promise.resolve([
                                        {
                                            data: [1],
                                            total: 1,
                                        },
                                    ]),
                            };
                        },
                    },
                    bulkEditApiFactory: {
                        getHandler: () => {
                            return {
                                bulkEdit: (selectedIds) => {
                                    if (isResponseError) {
                                        return Promise.reject(new Error('error occured'));
                                    }

                                    if (selectedIds.length === 0) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve();
                                },

                                bulkEditStatus: (selectedIds) => {
                                    if (isResponseError) {
                                        return Promise.reject(new Error('error occured'));
                                    }

                                    if (selectedIds.length === 0) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve();
                                },
                            };
                        },
                    },
                    orderDocumentApiService: {
                        create: () => {
                            return Promise.resolve();
                        },
                        download: () => {
                            return Promise.resolve();
                        },
                        extendingDeprecatedService: () => {
                            return Promise.resolve({
                                data: {
                                    showWarning: false,
                                },
                            });
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
            props: {
                title: 'Foo bar',
            },
        });
    }

    beforeAll(async () => {
        routes = [
            {
                name: 'sw.bulk.edit.order',
                path: '/index/:parentId?/:excludeDelivery?',
                component: {
                    template: '<div>sw-bulk-edit-order</div>',
                },
            },
            {
                name: 'sw.bulk.edit.order.save',
                path: '',
                component: await wrapTestComponent('sw-bulk-edit-save-modal', {
                    sync: true,
                }),
                meta: {
                    $module: {
                        title: 'sw-bulk-edit-order.general.mainMenuTitle',
                    },
                },
                redirect: {
                    name: 'sw.bulk.edit.order.save.confirm',
                },
                children: [
                    {
                        name: 'sw.bulk.edit.order.save.confirm',
                        path: '/confirm',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-confirm', { sync: true }),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-order.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.process',
                        path: '/process',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-process', { sync: true }),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-order.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.success',
                        path: '/success',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-success', { sync: true }),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-order.general.mainMenuTitle',
                            },
                        },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.error',
                        path: 'error',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal-error', { sync: true }),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-order.general.mainMenuTitle',
                            },
                        },
                    },
                ],
            },
        ];
    });

    beforeEach(async () => {
        const mockResponses = global.repositoryFactoryMock.responses;
        mockResponses.addResponse({
            method: 'post',
            url: '/search/document-type',
            status: 200,
            response: {
                data: [
                    {
                        id: Shopware.Utils.createId(),
                        attributes: {
                            id: Shopware.Utils.createId(),
                        },
                    },
                ],
            },
        });

        mockResponses.addResponse({
            method: 'Post',
            url: '/user-config',
            status: 200,
            response: {
                data: [],
            },
        });

        Shopware.State.commit('shopwareApps/setSelectedIds', [selectedOrderId]);
    });

    it('should show all form fields', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-renderer').exists()).toBeTruthy();
    });

    it('should disable status mails and documents by default', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(
            wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled,
        ).toBeDefined();
        expect(
            wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled,
        ).toBeDefined();
    });

    it('should enable status mails when one of the status fields has changed', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(
            wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled,
        ).toBeUndefined();
    });

    it('should enable documents when status mails is enabled', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1',
                },
            },
        });

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').setValue('checked');

        await wrapper.vm.$nextTick();

        expect(
            wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled,
        ).toBeUndefined();
    });

    it('should call onCustomFieldsChange when a customField is changed', async () => {
        wrapper = await createWrapper();

        const spyOnCustomFieldsChange = jest.spyOn(wrapper.vm, 'onCustomFieldsChange');

        await flushPromises();

        await wrapper.vm.$nextTick();

        await wrapper
            .find('.sw-bulk-edit__custom-fields .sw-bulk-edit-custom-fields__change .sw-field__checkbox input')
            .setValue('checked');

        await wrapper.vm.$nextTick();

        expect(spyOnCustomFieldsChange).toHaveBeenCalledTimes(1);
        wrapper.vm.onCustomFieldsChange.mockRestore();
        expect(wrapper.vm.bulkEditData.customFields.value).toHaveProperty('customFieldName');
    });

    it('should call onChangeDocument when a document field changed is changed', async () => {
        wrapper = await createWrapper();

        const spyOnChangeDocument = jest.spyOn(wrapper.vm, 'onChangeDocument');

        await flushPromises();

        await wrapper
            .find('.sw-bulk-edit-change-field-invoice .sw-bulk-edit-change-field__change input')
            .setValue('checked');

        await flushPromises();

        expect(spyOnChangeDocument).toHaveBeenCalledTimes(1);
        wrapper.vm.onChangeDocument.mockRestore();
    });

    it('should push selected document types to payload when documents is enabled', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1',
                },
                documents: {
                    isChanged: true,
                    value: '1',
                },
            },
            order: {
                documents: {
                    documentType: {
                        credit_note: true,
                    },
                },
            },
        });

        await wrapper.vm.$nextTick();

        const { statusData } = wrapper.vm.onProcessData();

        await wrapper.vm.$nextTick();

        const changeDocumentTypes = statusData[0].documentTypes;

        expect(changeDocumentTypes[0]).toBe('credit_note');
    });

    it('should not push selected document types to payload when documents is disable', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1',
                },
                documents: {
                    isChanged: false,
                },
            },
            order: {
                documents: {
                    documentType: {
                        credit_note: true,
                    },
                },
            },
        });

        await wrapper.vm.$nextTick();

        const { statusData } = wrapper.vm.onProcessData();

        await wrapper.vm.$nextTick();

        const changeDocumentTypes = statusData[0].documentTypes;

        expect(changeDocumentTypes).toBeUndefined();
    });

    it('should show empty state', async () => {
        wrapper = await createWrapper();

        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        await wrapper.setData({
            isLoading: false,
        });
        await flushPromises();

        expect(wrapper.vm.selectedIds).toHaveLength(0);

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.order.messageEmptyTitle');
    });

    it('should open confirm modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toBe('/confirm');
    });

    it('should close confirm modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        await footerLeft.find('button').trigger('click');

        await flushPromises();
        expect(wrapper.vm.$route.path).toBe('/index');
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeFalsy();
    });

    it('should open process and success modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$router.options.history.state.back).toBe('/process');
        expect(wrapper.vm.$route.path).toBe('/success');
    });

    it('should open error modal', async () => {
        wrapper = await createWrapper(true);
        await flushPromises();

        await wrapper.setData({
            bulkEditData: {
                ...wrapper.vm.bulkEditData,
                orderTransactions: {
                    isChanged: true,
                    value: '1',
                },
            },
        });

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toBe('/error');
    });

    it('should show tags and custom fields card', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tagsCard = wrapper.find('.sw-bulk-edit-order-base__tags');
        expect(tagsCard).toBeTruthy();

        const customFieldsCard = wrapper.find('.sw-card sw-bulk-edit-order-base__custom_fields');
        expect(customFieldsCard).toBeTruthy();

        wrapper.vm.bulkEditData.customFields.value = {
            field1: 'abc',
        };

        await tagsCard.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await wrapper.vm.$nextTick();

        const { syncData } = wrapper.vm.onProcessData();
        await wrapper.vm.$nextTick();

        const changeTagField = syncData[0];
        expect(changeTagField.field).toBe('tags');
        expect(changeTagField.type).toBe('overwrite');

        const changeCustomField = syncData[1];
        expect(changeCustomField.field).toBe('customFields');
        expect(changeCustomField.value).toBe(wrapper.vm.bulkEditData.customFields.value);
    });

    it('should set route meta module when component created', async () => {
        wrapper = await createWrapper();
        wrapper.vm.setRouteMetaModule = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.setRouteMetaModule).toHaveBeenCalled();
        expect(wrapper.vm.$route.meta.$module.color).toBe('#A092F0');
        expect(wrapper.vm.$route.meta.$module.icon).toBe('regular-shopping-bag');

        wrapper.vm.setRouteMetaModule.mockRestore();
    });

    it('should call fetchStatusOptions when component created', async () => {
        wrapper = await createWrapper();
        const fetchStatusOptionsSpy = jest.spyOn(wrapper.vm, 'fetchStatusOptions');
        await wrapper.vm.createdComponent();

        expect(fetchStatusOptionsSpy).toHaveBeenCalledTimes(3);
        expect(fetchStatusOptionsSpy).toHaveBeenNthCalledWith(1, 'orders.id');
        expect(fetchStatusOptionsSpy).toHaveBeenNthCalledWith(2, 'orderTransactions.orderId');
        expect(fetchStatusOptionsSpy).toHaveBeenNthCalledWith(3, 'orderDeliveries.orderId');

        const orderStateCriteria = new Criteria(1, null);
        const { liveVersionId } = Shopware.Context.api;

        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenCalledTimes(6);

        orderStateCriteria.addFilter(
            Criteria.multi('AND', [
                Criteria.equalsAny('orders.id', [selectedOrderId]),
                Criteria.equals('orders.versionId', liveVersionId),
            ]),
        );
        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenNthCalledWith(1, orderStateCriteria);

        const orderTransactionStateCriteria = new Criteria(1, null);
        orderTransactionStateCriteria.addFilter(
            Criteria.multi('AND', [
                Criteria.equalsAny('orderTransactions.orderId', [
                    selectedOrderId,
                ]),
                Criteria.equals('orderTransactions.orderVersionId', liveVersionId),
            ]),
        );
        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenNthCalledWith(2, orderTransactionStateCriteria);

        const orderDeliveryStateCriteria = new Criteria(1, null);
        orderDeliveryStateCriteria.addFilter(
            Criteria.multi('AND', [
                Criteria.equalsAny('orderDeliveries.orderId', [
                    selectedOrderId,
                ]),
                Criteria.equals('orderDeliveries.orderVersionId', liveVersionId),
            ]),
        );
        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenNthCalledWith(3, orderDeliveryStateCriteria);

        wrapper.vm.fetchStatusOptions.mockClear();
    });

    it('should disable processing button', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            bulkEditData: {
                orders: {
                    isChanged: false,
                },
                orderTransactions: {
                    isChanged: false,
                },
                orderDeliveries: {
                    isChanged: false,
                },
                statusMails: {
                    isChanged: false,
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-order__save-action').classes()).toContain('sw-button--disabled');

        await wrapper.setData({
            isLoading: false,
            bulkEditData: {
                orders: {
                    isChanged: true,
                },
                orderTransactions: {
                    isChanged: false,
                },
                orderDeliveries: {
                    isChanged: false,
                },
                statusMails: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-bulk-edit-order__save-action').classes()).not.toContain('sw-button--disabled');
    });

    it('should get latest order status correctly', async () => {
        wrapper = await createWrapper();
        wrapper.vm.fetchStatusOptions = jest.fn();

        await wrapper.setData({
            isLoading: false,
            bulkEditData: {
                orders: {
                    isChanged: true,
                },
                orderTransactions: {
                    isChanged: true,
                },
                orderDeliveries: {
                    isChanged: true,
                },
                statusMails: {
                    isChanged: false,
                },
            },
        });

        wrapper.vm.getLatestOrderStatus();

        expect(wrapper.vm.fetchStatusOptions).toHaveBeenCalledWith('orderTransactions.order.id');
        expect(wrapper.vm.fetchStatusOptions).toHaveBeenCalledWith('orderDeliveries.order.id');
        expect(wrapper.vm.fetchStatusOptions).toHaveBeenCalledWith('orders.id');
        wrapper.vm.fetchStatusOptions.mockRestore();
    });

    it('should restrict fields on including orders without delivery', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.statusFormFields).toHaveLength(5);
        expect(wrapper.vm.statusFormFields[1].name).toBe('orderDeliveries');

        await wrapper.vm.$router.push({
            name: 'sw.bulk.edit.order',
            params: { parentId: 'null', excludeDelivery: '1' },
        });

        await flushPromises();

        expect(wrapper.vm.statusFormFields).toHaveLength(4);
        expect(wrapper.vm.statusFormFields[1].name).not.toBe('orderDeliveries');
    });
});
