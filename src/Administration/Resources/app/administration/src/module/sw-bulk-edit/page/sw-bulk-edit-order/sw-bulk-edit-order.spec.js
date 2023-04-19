import { config, createLocalVue, mount } from '@vue/test-utils';
import VueRouter from 'vue-router';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/component/structure/sw-page';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-text-editor';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import swBulkEditOrder from 'src/module/sw-bulk-edit/page/sw-bulk-edit-order';
import swBulkEditCustomFields from 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import swBulkEditChangeTypeFieldRenderer from 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer';
import swBulkEditFormFieldRenderer from 'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer';
import swBulkEditChangeType from 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import swBulkEditOrderDocuments from 'src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents';
import 'src/app/component/form/sw-select-field';
import swBulkEditSaveModal from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import swBulkEditSaveModalConfirm from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm';
import swBulkEditSaveModalProcess from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';
import swBulkEditSaveModalSuccess from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';
import swBulkEditSaveModalError from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

Shopware.Component.register('sw-bulk-edit-order', swBulkEditOrder);
Shopware.Component.extend('sw-bulk-edit-custom-fields', 'sw-custom-field-set-renderer', swBulkEditCustomFields);
Shopware.Component.register('sw-bulk-edit-change-type-field-renderer', swBulkEditChangeTypeFieldRenderer);
Shopware.Component.extend('sw-bulk-edit-form-field-renderer', 'sw-form-field-renderer', swBulkEditFormFieldRenderer);
Shopware.Component.register('sw-bulk-edit-change-type', swBulkEditChangeType);
Shopware.Component.register('sw-bulk-edit-order-documents', swBulkEditOrderDocuments);
Shopware.Component.register('sw-bulk-edit-save-modal', swBulkEditSaveModal);
Shopware.Component.register('sw-bulk-edit-save-modal-confirm', swBulkEditSaveModalConfirm);
Shopware.Component.register('sw-bulk-edit-save-modal-process', swBulkEditSaveModalProcess);
Shopware.Component.register('sw-bulk-edit-save-modal-success', swBulkEditSaveModalSuccess);
Shopware.Component.register('sw-bulk-edit-save-modal-error', swBulkEditSaveModalError);

const selectedOrderId = Shopware.Utils.createId();

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

/**
 * @package system-settings
 */
describe('src/module/sw-bulk-edit/page/sw-bulk-edit-order', () => {
    let wrapper;
    let routes;
    let router;

    async function createWrapper(isResponseError = false) {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const localVue = createLocalVue();
        localVue.use(VueRouter);

        return mount(await Shopware.Component.build('sw-bulk-edit-order'), {
            localVue,
            router,
            stubs: {
                'sw-page': await Shopware.Component.build('sw-page'),
                'sw-loader': true,
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-select-field': await Shopware.Component.build('sw-select-field'),
                'sw-bulk-edit-custom-fields': await Shopware.Component.build('sw-bulk-edit-custom-fields'),
                'sw-bulk-edit-change-type-field-renderer': await Shopware.Component.build('sw-bulk-edit-change-type-field-renderer'),
                'sw-bulk-edit-form-field-renderer': await Shopware.Component.build('sw-bulk-edit-form-field-renderer'),
                'sw-bulk-edit-change-type': await Shopware.Component.build('sw-bulk-edit-change-type'),
                'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
                'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
                'sw-button-process': await Shopware.Component.build('sw-button-process'),
                'sw-bulk-edit-order-documents': await Shopware.Component.build('sw-bulk-edit-order-documents'),
                'sw-card': await Shopware.Component.build('sw-card'),
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-single-select': await Shopware.Component.build('sw-single-select'),
                'sw-number-field': await Shopware.Component.build('sw-number-field'),
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-textarea-field': await Shopware.Component.build('sw-textarea-field'),
                'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-container': await Shopware.Component.build('sw-container'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
                'sw-card-view': await Shopware.Component.build('sw-card-view'),
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
                'sw-tabs': await Shopware.Component.build('sw-tabs'),
                'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
                'sw-ignore-class': true,
                'sw-extension-component-section': true,
                'sw-bulk-edit-order-documents-generate-invoice': true,
                'sw-bulk-edit-order-documents-generate-cancellation-invoice': true,
                'sw-bulk-edit-order-documents-generate-delivery-note': true,
                'sw-bulk-edit-order-documents-generate-credit-note': true,
                'sw-bulk-edit-order-documents-download-documents': true,
                'sw-entity-tag-select': true,
                'sw-inherit-wrapper': true,
                'sw-error-summary': true,
            },
            props: {
                title: 'Foo bar',
            },
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'custom_field_set') {
                            return {
                                search: () => Promise.resolve(createEntityCollection([{
                                    id: 'field-set-id-1',
                                    name: 'example',
                                    customFields: [{
                                        name: 'customFieldName',
                                        type: 'text',
                                        config: {
                                            label: 'configFieldLabel',
                                        },
                                    }],
                                }])),
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
                                        search: () => Promise.resolve([{ id: 'field-set-id-1' }]),
                                        get: () => Promise.resolve({ id: '' }),
                                    };
                                }

                                return {
                                    id: '1a2b3c',
                                    name: 'Test order',
                                };
                            },
                            search: () => Promise.resolve([
                                {
                                    id: 1,
                                    name: 'Invoice',
                                },
                                {
                                    id: 2,
                                    name: 'Credit note',
                                },
                            ]),
                            get: () => Promise.resolve({
                                id: 1,
                                name: 'Order',
                            }),
                            searchIds: () => Promise.resolve([
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
        });
    }

    beforeAll(async () => {
        routes = [
            {
                name: 'sw.bulk.edit.order',
                path: 'index',
            },
            {
                name: 'sw.bulk.edit.order.save',
                path: '',
                component: await Shopware.Component.build('sw-bulk-edit-save-modal'),
                meta: { $module: {
                    title: 'sw-bulk-edit-order.general.mainMenuTitle',
                } },
                redirect: {
                    name: 'sw.bulk.edit.order.save.confirm',
                },
                children: [
                    {
                        name: 'sw.bulk.edit.order.save.confirm',
                        path: 'confirm',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-confirm'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-order.general.mainMenuTitle',
                        } },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.process',
                        path: 'process',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-process'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-order.general.mainMenuTitle',
                        } },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.success',
                        path: 'success',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-success'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-order.general.mainMenuTitle',
                        } },
                    },
                    {
                        name: 'sw.bulk.edit.order.save.error',
                        path: 'error',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-error'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-order.general.mainMenuTitle',
                        } },
                    },
                ],
            },
        ];
        router = new VueRouter({
            routes,
        });
        const orgPush = router.push;
        router.push = (location) => {
            return orgPush.call(router, location).catch(() => {});
        };
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

    afterEach(() => {
        wrapper.destroy();
        wrapper.vm.$router.push({ path: 'confirm' });
    });

    it('should show all form fields', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-renderer').exists()).toBeTruthy();
    });

    it('should disable status mails and documents by default', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled).toBeTruthy();
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

        expect(wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').attributes().disabled).toBeFalsy();
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

        await wrapper.find('.sw-bulk-edit-change-field-statusMails .sw-field__checkbox input').setChecked();

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-change-field-documents .sw-field__checkbox input').attributes().disabled).toBeFalsy();
    });

    it('should call onCustomFieldsChange when a customField is changed', async () => {
        wrapper = await createWrapper();

        const spyOnCustomFieldsChange = jest.spyOn(wrapper.vm, 'onCustomFieldsChange');

        await flushPromises();

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-bulk-edit__custom-fields .sw-bulk-edit-custom-fields__change .sw-field__checkbox input').setChecked();

        await wrapper.vm.$nextTick();

        expect(spyOnCustomFieldsChange).toHaveBeenCalledTimes(1);
        wrapper.vm.onCustomFieldsChange.mockRestore();
        expect(wrapper.vm.bulkEditData.customFields.value).toHaveProperty('customFieldName');
    });

    it('should call onChangeDocument when a document field changed is changed', async () => {
        wrapper = await createWrapper();

        const spyOnChangeDocument = jest.spyOn(wrapper.vm, 'onChangeDocument');

        await flushPromises();

        await wrapper.find('.sw-bulk-edit-change-field-invoice .sw-bulk-edit-change-field__change input').setChecked();

        await wrapper.vm.$nextTick();

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

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.$route.path).toBe('index');
        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeFalsy();
    });

    it('should open process modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toBe('/process');
    });

    it('should open success modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-order__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

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

        await tagsCard.find('.sw-bulk-edit-change-field__change input').setChecked();
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

        orderStateCriteria.addFilter(Criteria.equalsAny('orders.id', [selectedOrderId]));
        orderStateCriteria.addFilter(Criteria.equals('orders.versionId', liveVersionId));
        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenNthCalledWith(1, orderStateCriteria);

        const orderTransactionStateCriteria = new Criteria(1, null);
        orderTransactionStateCriteria.addFilter(Criteria.equalsAny('orderTransactions.orderId', [selectedOrderId]));
        orderTransactionStateCriteria.addFilter(Criteria.equals('orderTransactions.orderVersionId', liveVersionId));
        expect(wrapper.vm.stateMachineStateRepository.searchIds).toHaveBeenNthCalledWith(2, orderTransactionStateCriteria);

        const orderDeliveryStateCriteria = new Criteria(1, null);
        orderDeliveryStateCriteria.addFilter(Criteria.equalsAny('orderDeliveries.orderId', [selectedOrderId]));
        orderDeliveryStateCriteria.addFilter(Criteria.equals('orderDeliveries.orderVersionId', liveVersionId));
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

        wrapper.vm.$router.push({ name: 'sw.bulk.edit.order', params: { parentId: 'null', excludeDelivery: '1' } });

        await flushPromises();

        expect(wrapper.vm.statusFormFields).toHaveLength(4);
        expect(wrapper.vm.statusFormFields[1].name).not.toBe('orderDeliveries');
    });
});
