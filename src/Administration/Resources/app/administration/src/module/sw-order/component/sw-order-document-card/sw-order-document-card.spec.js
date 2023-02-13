import { shallowMount, createLocalVue } from '@vue/test-utils';
import swOrderDocumentCard from 'src/module/sw-order/component/sw-order-document-card';
import swOrderSelectDocumentTypeModal from 'src/module/sw-order/component/sw-order-select-document-type-modal';
import swOrderDocumentSettingsModal from 'src/module/sw-order/component/sw-order-document-settings-modal';
import swOrderDocumentSettingsInvoiceModal from 'src/module/sw-order/component/sw-order-document-settings-invoice-modal';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-group';
import EntityCollection from 'src/core/data/entity-collection.data';

import orderDetailStore from 'src/module/sw-order/state/order-detail.store';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-document-card', swOrderDocumentCard);
Shopware.Component.register('sw-order-select-document-type-modal', swOrderSelectDocumentTypeModal);
Shopware.Component.register('sw-order-document-settings-modal', swOrderDocumentSettingsModal);
Shopware.Component.extend('sw-order-document-settings-invoice-modal', 'sw-order-document-settings-modal', swOrderDocumentSettingsInvoiceModal);

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null
    );
}

const orderFixture = {
    id: '1234',
    documents: [],
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: [],
    deepLinkCode: 'abcdef'
};

const documentFixture = {
    orderId: 'order1',
    sent: true,
    documentMediaFileId: null,
    documentType: {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
    },
    config: {
        documentNumber: '1000'
    },
    id: 'document1',
    deepLinkCode: 'abcd',
};

const documentTypeFixture = [
    {
        id: '0',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: {
            name: 'Delivery note',
        }
    },
    {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
        translated: {
            name: 'Invoice',
        }
    },
    {
        id: '2',
        name: 'Cancellation invoice',
        technicalName: 'storno',
        translated: {
            name: 'Cancellation invoice',
        }
    },
    {
        id: '3',
        name: 'Credit note',
        technicalName: 'credit_note',
        translated: {
            name: 'Credit note',
        }
    },
];

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        inserted(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        },
        update(el, binding) {
            el.setAttribute('tooltip-message', binding.value.message);
        }
    });

    return shallowMount(await Shopware.Component.build('sw-order-document-card'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-empty-state': {
                template: '<div class="sw-empty-state"><slot name="icon"></slot><slot name="actions"></slot></div>'
            },
            'sw-card-section': {
                template: '<div class="sw-card-section"><slot></slot></div>'
            },
            'sw-card-filter': {
                template: '<div class="sw-card-filter"><slot name="filter"></slot></div>'
            },
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': true,
            'sw-context-button': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-order-select-document-type-modal': await Shopware.Component.build('sw-order-select-document-type-modal'),
            'sw-order-send-document-modal': true,
            'sw-order-document-settings-modal': await Shopware.Component.build('sw-order-document-settings-modal'),
            'sw-order-document-settings-delivery-note-modal': true,
            'sw-order-document-settings-invoice-modal': await Shopware.Component.build('sw-order-document-settings-invoice-modal'),
            'sw-order-document-settings-credit-note-modal': true,
            'sw-order-document-settings-storno-modal': true,
            'sw-data-grid': {
                props: ['dataSource', 'columns'],
                template: `
                    <div class="sw-data-grid">
                    <table>
                        <thead class="sw-data-grid__header">
                        <th
                            v-for="(column) in columns"
                            class="sw-data-grid__cell--header"
                            :key="column.property"
                        >
                            {{ column.label }}
                        </th>
                        </thead>

                        <tbody class="sw-data-grid__body">
                            <td
                                v-for="item in dataSource"
                                class="sw-data-grid__cell"
                            >
                                <slot></slot>
                                <slot name="column-sent" v-bind="{ item }"></slot>
                                <slot name="actions" v-bind="{ item }"></slot>
                            </td>
                        </tbody>
                    </table>
                    </div>
                `
            },
            'sw-data-grid-column-boolean': true,
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`
            },
            'sw-radio-field': true,
            'sw-datepicker': true,
            'sw-icon': true,
            'sw-textarea-field': true,
            'sw-switch-field': true,
            'sw-button-group': await Shopware.Component.build('sw-button-group'),
            'sw-loader': true,
        },
        provide: {
            acl: {
                can: (key) => {
                    if (!key) { return true; }

                    return privileges.includes(key);
                }
            },
            documentService: {
                setListener: () => ({}),
                getDocument: () => Promise.resolve({
                    headers: {
                        'content-disposition': 'attachment; filename=dummny.pdf'
                    },
                    data: 'https://shopware.test/dummny.pdf'
                }),
                createDocument: () => Promise.resolve({ data: {
                    documentId: '1234',
                    documentDeepLink: '12341234'
                } }),
            },
            numberRangeService: {
                reserve: () => Promise.resolve({ number: 1000 })
            },
            repositoryFactory: {
                create: (entity) => ({
                    search: () => {
                        if (entity === 'document_type') {
                            return Promise.resolve(getCollection('document_type', documentTypeFixture));
                        }

                        return Promise.resolve([]);
                    },
                    get: () => {
                        if (entity === 'document') {
                            return Promise.resolve(documentTypeFixture);
                        }

                        return Promise.resolve({});
                    },
                    save: () => Promise.resolve({}),
                    searchIds: () => Promise.resolve([])
                }),
            },
            searchRankingService: {}
        },
        mocks: {
            $route: {
                query: ''
            }
        },
        propsData: {
            order: orderFixture,
            isLoading: false
        }
    });
}

describe('src/module/sw-order/component/sw-order-document-card', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', {
            ...orderDetailStore,
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an disabled create new button', async () => {
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBe('disabled');
    });

    it('should not have an disabled create new button', async () => {
        wrapper = await createWrapper([
            'order.editor',
            'document.viewer',
        ]);
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBeUndefined();
    });

    it('should show the error of invoice number is existing', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: []
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of credit note number is existing', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: []
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of delivery note number is existing', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: []
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of cancellation invoice number is existing', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: []
                }
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should save document when the event return finished', async () => {
        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-finished'
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showModal).toBeFalsy();

        // Wait 2 ticks for parent component to update
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('document-save')).toBeTruthy();
    });

    it('should show Select document type modal when click on Create new button', async () => {
        wrapper = await createWrapper([
            'order.editor',
            'document.viewer',
        ]);

        const createNewButton = wrapper.find('.sw-order-document-grid-button');
        await createNewButton.trigger('click');

        const documentTypeSelectModal = wrapper.find('.sw-order-select-document-type-modal');
        expect(documentTypeSelectModal.exists()).toBeTruthy();
    });

    it('should show modal regarding to current document type', async () => {
        await wrapper.setData({
            currentDocumentType: {
                id: '0',
                name: 'Delivery note',
                technicalName: 'delivery_note',
                translated: {
                    name: 'Delivery note',
                }
            },
            showModal: true,
        });

        expect(wrapper.find('sw-order-document-settings-delivery-note-modal-stub').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                }
            },
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '2',
                name: 'Cancellation invoice',
                technicalName: 'storno',
                translated: {
                    name: 'Cancellation invoice',
                }
            },
        });

        expect(wrapper.find('sw-order-document-settings-storno-modal-stub').exists()).toBeTruthy();

        await wrapper.setData({
            currentDocumentType: {
                id: '3',
                name: 'Credit note',
                technicalName: 'credit_note',
                translated: {
                    name: 'Credit note',
                }
            },
        });

        expect(wrapper.find('sw-order-document-settings-credit-note-modal-stub').exists()).toBeTruthy();
    });

    it('should show Send document modal when click on Send document option', async () => {
        wrapper = await createWrapper([
            'order.editor'
        ]);

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture
            ])
        });

        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();

        const sendDocumentButton = wrapper.findAll('.sw-context-menu-item').at(2);
        await sendDocumentButton.trigger('click');

        const sendDocumentModal = wrapper.find('sw-order-send-document-modal-stub');
        expect(sendDocumentModal.exists()).toBeTruthy();
        expect(wrapper.vm.sendDocument).toEqual(documentFixture);
    });

    it('should show attach column when attachView is true', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture
            ])
        });

        let columns = wrapper.findAll('.sw-data-grid__cell--header');
        expect(columns.length).toEqual(4);

        await wrapper.setProps({
            attachView: true,
        });

        columns = wrapper.findAll('.sw-data-grid__cell--header');
        expect(columns.length).toEqual(5);
        expect(columns.wrappers.at(4).text()).toEqual('sw-order.documentCard.labelAttach');
    });

    it('should show card filter when order has document', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-card-filter').exists()).toBeFalsy();

        await wrapper.setProps({
            order: {
                documents: getCollection('document', [
                    documentFixture
                ])
            },
        });

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture
            ])
        });

        expect(wrapper.find('.sw-card-filter').exists()).toBeTruthy();
    });

    it('should change sent status when click on "Mark as unsent" context menu', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture
            ]),
        });

        const contextMenu = wrapper.findAll('.sw-context-menu-item');

        expect(wrapper.find('sw-data-grid-column-boolean-stub').attributes().value).toBeTruthy();

        // Mark as sent option is disabled
        expect(contextMenu.at(3).attributes().disabled).toEqual('disabled');

        // Mark as unsent
        await contextMenu.at(4).trigger('click');

        expect(wrapper.find('sw-data-grid-column-boolean-stub').attributes().value).toBeFalsy();
        expect(contextMenu.at(4).attributes().disabled).toEqual('disabled');
    });

    it('should change sent status when click on "Mark as sent" context menu', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    sent: false,
                }
            ]),
        });

        const contextMenu = wrapper.findAll('.sw-context-menu-item');

        expect(wrapper.find('sw-data-grid-column-boolean-stub').attributes().value).toBeFalsy();

        // Mark as unsent option is disabled
        expect(contextMenu.at(4).attributes().disabled).toEqual('disabled');

        // Mark as unsent
        await contextMenu.at(3).trigger('click');

        expect(wrapper.find('sw-data-grid-column-boolean-stub').attributes().value).toBeTruthy();
        expect(contextMenu.at(3).attributes().disabled).toEqual('disabled');
    });

    it('should show Send mail modal when choosing option Create and send in Create document modal ', async () => {
        wrapper = await createWrapper([
            'order.editor'
        ]);

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                }
            },
            showModal: true,
        });


        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();
        await wrapper.find('.sw-order-document-settings-modal__send-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-order-send-document-modal-stub').exists()).toBeTruthy();
    });

    it('should call downloadDocument method when choosing option Create and download in Create document modal', async () => {
        wrapper = await createWrapper([
            'order.editor'
        ]);

        wrapper.vm.downloadDocument = jest.fn();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                }
            },
            showModal: true,
        });


        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();
        await wrapper.find('.sw-order-document-settings-modal__download-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.downloadDocument).toHaveBeenCalled();
        wrapper.vm.downloadDocument.mockRestore();
    });

    it('should show permission tooltip message on Create document button correctly', async () => {
        wrapper = await createWrapper();

        const buttonCreate = wrapper.find('.sw-order-document-grid-button');
        expect(buttonCreate.attributes('tooltip-message')).toEqual('sw-privileges.tooltip.warning');
        expect(buttonCreate.attributes('disabled')).toBeTruthy();
    });

    it('should show order unsaved tooltip message on Create document button correctly', async () => {
        wrapper = await createWrapper([
            'order.editor',
            'document.viewer',
        ]);

        Shopware.State.commit('swOrderDetail/setEditing', true);
        await wrapper.vm.$nextTick();

        const buttonCreate = wrapper.find('.sw-order-document-grid-button');
        expect(buttonCreate.attributes()['tooltip-message']).toEqual('sw-order.documentTab.tooltipSaveBeforeCreateDocument');
        expect(buttonCreate.attributes('disabled')).toBeTruthy();
    });

    it('should search documents with criteria queries', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.documentCriteria.term).toBeNull();
        expect(wrapper.vm.documentCriteria.queries).toEqual([]);

        await wrapper.setData({
            term: '1000',
        });

        expect(wrapper.vm.documentCriteria.term).toEqual('1000');
        expect(wrapper.vm.documentCriteria.queries).toEqual([
            {
                score: 500,
                query: { type: 'contains', field: 'config.documentDate', value: '1000' }
            },
            {
                score: 500,
                query: { type: 'equals', field: 'config.documentNumber', value: '1000' }
            }
        ]);
    });
});
