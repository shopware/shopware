/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { createPinia, setActivePinia } from 'pinia';
import { DOCUMENT_TYPES } from '../../order.types';

function getCollection(entity, collection) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
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
    deepLinkCode: 'abcdef',
    versionId: 'order-version-id',
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
        documentNumber: '1000',
        documentDate: '2023/01/01',
    },
    id: 'document1',
    deepLinkCode: 'abcd',
    documentMediaFile: {
        id: '1234',
        fileExtension: 'pdf',
    },
    documentA11yMediaFile: {
        id: '12345',
        fileExtension: 'html',
    },
};

const documentV2Fixture = {
    ...documentFixture,
    documentMediaFile: null,
    documentA11yMediaFile: null,
    documentFiles: [
        {
            documentFormat: 'html',
        },
        {
            documentFormat: 'zugferd_xml',
        },
    ],
};

const documentTypeFixture = [
    {
        id: '0',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: {
            name: 'Delivery note',
        },
    },
    {
        id: '1',
        name: 'Invoice',
        technicalName: 'invoice',
        translated: {
            name: 'Invoice',
        },
    },
    {
        id: '2',
        name: 'Cancellation invoice',
        technicalName: 'storno',
        translated: {
            name: 'Cancellation invoice',
        },
    },
    {
        id: '3',
        name: 'Credit note',
        technicalName: 'credit_note',
        translated: {
            name: 'Credit note',
        },
    },
];

const defaultProps = {
    order: orderFixture,
    isLoading: false,
};

const buttonDeleteClassEntityListing = '.sw-entity-listing__context-menu-edit-delete';
const buttonDeleteClassDocumentCard = '.sw-order-document-card__context-button-delete';

let documentSearchMock;
let documentDeleteMock;
let createDocumentMock;
let createDocumentV2Mock;
let uploadDocumentV2Mock;
let getDocumentV2Mock;
let getDocumentPreviewV2Mock;

async function createWrapper(props = defaultProps, routeName = 'sw.order.detail.details') {
    documentSearchMock = jest.fn().mockResolvedValue(getCollection('document_type', documentTypeFixture));
    documentDeleteMock = jest.fn().mockResolvedValue([]);
    createDocumentMock = jest.fn().mockResolvedValue({
        data: {
            documentId: '1234',
            documentDeepLink: '12341234',
        },
    });
    createDocumentV2Mock = jest.fn().mockResolvedValue({
        data: {
            documentId: '1234',
            documentDeepLink: '12341234',
            formats: ['html'],
        },
    });
    uploadDocumentV2Mock = jest.fn().mockResolvedValue({
        data: {
            documentId: '1234',
            deepLinkCode: '12341234',
            formats: ['pdf'],
        },
    });
    getDocumentV2Mock = jest.fn().mockResolvedValue({
        headers: {
            'content-disposition': 'attachment; filename=dummny.html',
        },
        data: 'https://shopware.test/dummny.html',
    });
    getDocumentPreviewV2Mock = jest.fn().mockResolvedValue({
        data: 'https://shopware.test/dummny.html',
    });

    const wrapper = mount(await wrapTestComponent('sw-order-document-card', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-card-section': {
                    template: '<div class="sw-card-section"><slot></slot></div>',
                },
                'sw-card-filter': {
                    template: '<div class="sw-card-filter"><slot name="filter"></slot></div>',
                },
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-order-select-document-type-modal': await wrapTestComponent('sw-order-select-document-type-modal', {
                    sync: true,
                }),
                'sw-order-create-document-modal': true,
                'sw-order-upload-document-modal': true,
                'sw-order-send-document-modal': true,
                'sw-order-document-settings-modal': await wrapTestComponent('sw-order-document-settings-modal', {
                    sync: true,
                }),
                'sw-order-document-settings-delivery-note-modal': true,
                'sw-order-document-settings-invoice-modal': await wrapTestComponent(
                    'sw-order-document-settings-invoice-modal',
                    { sync: true },
                ),
                'sw-order-document-settings-credit-note-modal': await wrapTestComponent(
                    'sw-order-document-settings-credit-note-modal',
                ),
                'sw-order-document-settings-storno-modal': await wrapTestComponent(
                    'sw-order-document-settings-storno-modal',
                ),
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-bulk-edit-modal': await wrapTestComponent('sw-bulk-edit-modal', { sync: true }),
                'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                'sw-data-grid-column-boolean': {
                    props: ['value'],
                    template: '<div class="sw-data-grid-column-boolean"><slot></slot></div>',
                },
                'sw-context-menu-item': {
                    emits: ['click'],
                    template: `
                        <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                            <slot></slot>
                        </div>`,
                },
                'mt-dropdown-menu-root': {
                    template: '<div class="mt-dropdown-menu-root"><slot></slot></div>',
                },
                'mt-dropdown-menu-trigger': {
                    template: '<div class="mt-dropdown-menu-trigger"><slot></slot></div>',
                },
                'mt-dropdown-menu-sub': {
                    template: '<div class="mt-dropdown-menu-sub"><slot></slot></div>',
                },
                'mt-dropdown-menu-portal': {
                    template: '<div class="mt-dropdown-menu-portal"><slot></slot></div>',
                },
                'mt-action-menu': {
                    template: '<div class="mt-action-menu"><slot></slot></div>',
                },
                'mt-action-menu-group': {
                    template: '<div class="mt-action-menu-group"><slot></slot></div>',
                },
                'mt-action-menu-item': {
                    props: {
                        disabled: {
                            type: Boolean,
                            default: false,
                        },
                        icon: {
                            type: String,
                            required: false,
                            default: null,
                        },
                        isSubTrigger: {
                            type: Boolean,
                            default: false,
                        },
                        variant: {
                            type: String,
                            required: false,
                            default: 'default',
                        },
                    },
                    emits: ['select'],
                    template: `
                        <div
                            class="mt-action-menu-item"
                            :disabled="String(disabled)"
                            @click="$emit('select')"
                        >
                            <slot></slot>
                        </div>`,
                },
                'sw-radio-field': true,
                'sw-datepicker': true,
                'sw-textarea-field': true,

                'sw-button-group': await wrapTestComponent('sw-button-group', { sync: true }),
                'sw-loader': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'router-link': true,
                'sw-checkbox-field': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-inline-edit': true,
                'sw-data-grid-skeleton': true,
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
                'sw-time-ago': true,
            },
            provide: {
                documentService: {
                    setListener: () => ({}),
                    getDocument: () =>
                        Promise.resolve({
                            headers: {
                                'content-disposition': 'attachment; filename=dummny.pdf',
                            },
                            data: 'https://shopware.test/dummny.pdf',
                        }),
                    createDocument: (...args) => createDocumentMock(...args),
                },
                documentV2Service: {
                    setListener: () => ({}),
                    getDocument: (...args) => getDocumentV2Mock(...args),
                    previewDocument: (...args) => getDocumentPreviewV2Mock(...args),
                    createDocument: (...args) => createDocumentV2Mock(...args),
                    uploadDocument: (...args) => uploadDocumentV2Mock(...args),
                },
                numberRangeService: {
                    reserve: () => Promise.resolve({ number: 1000 }),
                },
                repositoryFactory: {
                    create: (entity) => ({
                        search: (...args) => {
                            if (entity === 'document_type') {
                                return Promise.resolve(getCollection('document_type', documentTypeFixture));
                            }

                            return documentSearchMock(...args);
                        },
                        get: () => {
                            if (entity === 'document') {
                                return Promise.resolve(documentTypeFixture);
                            }

                            return Promise.resolve({});
                        },
                        save: () => Promise.resolve({}),
                        searchIds: () => Promise.resolve([]),
                        delete: (...args) => documentDeleteMock(...args),
                    }),
                },
                searchRankingService: {
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
            },
            mocks: {
                $route: {
                    query: '',
                    name: routeName,
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    mounted(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                    updated(el, binding) {
                        el.setAttribute('tooltip-message', binding.value.message);
                    },
                },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-order/component/sw-order-document-card', () => {
    let wrapper;

    beforeAll(() => {
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('[sw-data-grid] Can not resolve accessor');
            },
        });

        setActivePinia(createPinia());
    });

    afterEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should have an disabled create new button', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        const createNewButton = wrapper.findComponent('.sw-order-document-grid-button');
        expect(createNewButton.attributes('disabled')).toBeDefined();
    });

    it('should not have an disabled create new button', async () => {
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();
        const createNewButton = wrapper.find('.sw-order-document-grid-button');

        expect(createNewButton.attributes().disabled).toBeUndefined();
    });

    it('should show the error of invoice number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of credit note number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of delivery note number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should show the error of cancellation invoice number is existing', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-fail',
            payload: {
                code: 'DOCUMENT__NUMBER_ALREADY_EXISTS',
                detail: 'error message',
                meta: {
                    parameters: [],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-order.documentCard.error.DOCUMENT__NUMBER_ALREADY_EXISTS',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should save document when the event return finished', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.convertStoreEventToVueEvent({
            action: 'create-document-finished',
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showModal).toBeFalsy();

        // Wait 3 ticks for parent component to update
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('document-save')).toBeTruthy();
    });

    it('should show Select document type modal when click on Create new button', async () => {
        global.activeFeatureFlags = [];
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        const createNewButton = wrapper.find('.sw-order-document-grid-button');
        await createNewButton.trigger('click');

        const documentTypeSelectModal = wrapper.find('.sw-order-select-document-type-modal');
        expect(documentTypeSelectModal.exists()).toBeTruthy();
    });

    it('should show the reworked create document modal when the feature flag is active', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        const createNewButton = wrapper.find('.sw-order-document-grid-button');
        await createNewButton.trigger('click');

        const createDocumentModal = wrapper.find('sw-order-create-document-modal-stub');
        expect(createDocumentModal.exists()).toBeTruthy();
    });

    it('should show the upload document modal from the generate button dropdown', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        await wrapper.find('.sw-order-document-grid-button-upload').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-order-upload-document-modal-stub').exists()).toBeTruthy();
    });

    it('should show Send document modal when click on Send document option', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });
        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();

        const sendDocumentButton = wrapper.find('.sw-order-document-card__context-button-send');
        await sendDocumentButton.trigger('click');

        const sendDocumentModal = wrapper.find('sw-order-send-document-modal-stub');
        expect(sendDocumentModal.exists()).toBeTruthy();
        expect(wrapper.vm.sendDocument).toEqual(documentFixture);
    });

    it('should show file types on order documents route', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        const columns = wrapper.findAll('.sw-data-grid__cell--header');
        // 5 data columns + 1 action column
        expect(columns).toHaveLength(6);
        expect(columns[3].text()).toBe('sw-order.documentCard.labelAvailableFormats');
    });

    it('should derive available formats from V2 document files', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        expect(wrapper.vm.availableFormatsFilter(documentV2Fixture)).toBe('HTML, ZUGFERD_XML');
    });

    it('should render open and download actions for each available format', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                documentV2Fixture,
            ]),
        });

        const openFormatActions = wrapper.findAll('.sw-order-document-card__context-button-open-format');
        expect(openFormatActions).toHaveLength(2);
        expect(openFormatActions.map((action) => action.text())).toEqual(
            expect.arrayContaining(['sw-order.documentCard.labelOpenFormat']),
        );

        const downloadFormatActions = wrapper.findAll('.sw-order-document-card__context-button-download-format');
        expect(downloadFormatActions).toHaveLength(2);
        expect(downloadFormatActions.map((action) => action.text())).toEqual(
            expect.arrayContaining(['sw-order.documentCard.labelDownloadFormat']),
        );

        expect(wrapper.vm.getDocumentFormatLabel('html')).toBe('HTML');
        expect(wrapper.vm.getDocumentFormatLabel('zugferd_xml')).toBe('ZUGFeRD XML');

        expect(wrapper.find('.sw-order-document-card__context-button-download-all-formats').exists()).toBe(true);
    });

    it('should open V2 documents from the format action', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        URL.createObjectURL = jest.fn().mockReturnValue('blob:open');
        const dispatchEventSpy = jest.spyOn(HTMLAnchorElement.prototype, 'dispatchEvent').mockImplementation(() => true);
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                documentV2Fixture,
            ]),
        });

        await wrapper.find('.sw-order-document-card__context-button-open-format').trigger('click');

        expect(getDocumentV2Mock).toHaveBeenCalledWith('document1', 'abcd', 'html');
        dispatchEventSpy.mockRestore();
    });

    it('should download V2 documents from the format action', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        URL.createObjectURL = jest.fn().mockReturnValue('blob:download');
        const dispatchEventSpy = jest.spyOn(HTMLAnchorElement.prototype, 'dispatchEvent').mockImplementation(() => true);
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                documentV2Fixture,
            ]),
        });

        await wrapper.find('.sw-order-document-card__context-button-download-format').trigger('click');

        expect(getDocumentV2Mock).toHaveBeenCalledWith('document1', 'abcd', 'html');
        dispatchEventSpy.mockRestore();
    });

    it('should only show the download all action when multiple formats are available', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    documentMediaFile: { fileExtension: 'pdf' },
                    documentA11yMediaFile: null,
                },
            ]),
        });

        expect(wrapper.find('.sw-order-document-card__context-button-download-all-formats').exists()).toBe(false);
    });

    it('should download every available format when using the download all action', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        const downloadDocumentSpy = jest.spyOn(wrapper.vm, 'downloadDocument').mockImplementation(() => {});

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    documentFiles: [
                        {
                            documentFormat: 'html',
                        },
                        {
                            documentFormat: 'pdf',
                        },
                    ],
                    documentMediaFile: null,
                    documentA11yMediaFile: null,
                },
            ]),
        });

        await wrapper.find('.sw-order-document-card__context-button-download-all-formats').trigger('click');

        expect(downloadDocumentSpy).toHaveBeenCalledTimes(2);
        expect(downloadDocumentSpy).toHaveBeenNthCalledWith(1, 'document1', 'abcd', 'pdf');
        expect(downloadDocumentSpy).toHaveBeenNthCalledWith(2, 'document1', 'abcd', 'html');

        downloadDocumentSpy.mockRestore();
    });

    it('should use the V2 create endpoint when the feature flag is active', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        URL.createObjectURL = jest.fn().mockReturnValue('blob:download');
        const dispatchEventSpy = jest.spyOn(HTMLAnchorElement.prototype, 'dispatchEvent').mockImplementation(() => true);
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'invoice',
            },
        });

        await wrapper.vm.onCreateDocument(
            {
                documentComment: '',
                documentDate: '2026-07-06T00:00:00.000Z',
                documentNumber: '1000',
                requestedFormats: ['html'],
            },
            'download',
        );

        expect(createDocumentMock).not.toHaveBeenCalled();
        expect(createDocumentV2Mock).toHaveBeenCalledWith(
            '1234',
            'order-version-id',
            'invoice',
            ['html'],
            '1000',
            '2026-07-06T00:00:00.000Z',
            '',
        );
        expect(getDocumentV2Mock).toHaveBeenCalledWith('1234', '12341234', 'html');
        dispatchEventSpy.mockRestore();
    });

    it('should open the send modal after creating a V2 document', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'invoice',
            },
        });

        await wrapper.vm.onCreateDocument(
            {
                documentComment: '',
                documentDate: '2026-07-06T00:00:00.000Z',
                documentNumber: '1000',
                requestedFormats: ['html'],
            },
            'send',
        );
        await flushPromises();

        expect(createDocumentMock).not.toHaveBeenCalled();
        expect(createDocumentV2Mock).toHaveBeenCalledWith(
            '1234',
            'order-version-id',
            'invoice',
            ['html'],
            '1000',
            '2026-07-06T00:00:00.000Z',
            '',
        );
        expect(wrapper.vm.showSendDocumentModal).toBe(true);
    });

    it('should use the V2 upload endpoint for uploaded custom documents', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'invoice',
            },
        });

        const file = new File(['pdf'], 'document.pdf', { type: 'application/pdf' });

        await wrapper.vm.onCreateDocument(
            {
                documentComment: '',
                documentDate: '2026-07-06T00:00:00.000Z',
                documentMediaFileId: null,
                documentNumber: '1000',
                requestedFormats: ['pdf'],
            },
            false,
            'referenced-document-id',
            file,
        );

        expect(createDocumentV2Mock).not.toHaveBeenCalled();
        expect(createDocumentMock).not.toHaveBeenCalled();
        expect(uploadDocumentV2Mock).toHaveBeenCalledWith(
            '1234',
            'order-version-id',
            'invoice',
            'pdf',
            '1000',
            '2026-07-06T00:00:00.000Z',
            '',
            null,
            file,
            'referenced-document-id',
        );
    });

    it('should use the V2 preview endpoint when the feature flag is active', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        URL.createObjectURL = jest.fn().mockReturnValue('blob:preview');
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'invoice',
            },
        });

        await wrapper.vm.onPreview(
            {
                documentComment: '',
                documentDate: '2026-07-06T00:00:00.000Z',
                documentNumber: '1000',
            },
            'html',
        );

        expect(getDocumentPreviewV2Mock).toHaveBeenCalledWith(
            '1234',
            'order-version-id',
            'invoice',
            'html',
            '1000',
            '2026-07-06T00:00:00.000Z',
            '',
        );
    });

    it('should not fail when the V2 preview endpoint handles an error', async () => {
        global.activeFeatureFlags = ['DOCUMENT_GENERATION_REWORK'];
        wrapper = await createWrapper();
        getDocumentPreviewV2Mock.mockResolvedValue(undefined);

        await wrapper.setData({
            currentDocumentType: {
                technicalName: 'invoice',
            },
        });

        await expect(
            wrapper.vm.onPreview(
                {
                    documentComment: '',
                    documentDate: '2026-07-06T00:00:00.000Z',
                    documentNumber: '1000',
                },
                'html',
            ),
        ).resolves.toBeUndefined();
        expect(wrapper.vm.isLoadingPreview).toBe(false);
    });

    it('should show attach column when attachView is true', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        let columns = wrapper.findAll('.sw-data-grid__cell--header');
        // 4 data columns + 1 action column
        expect(columns).toHaveLength(5);

        await wrapper.setProps({
            attachView: true,
        });

        columns = wrapper.findAll('.sw-data-grid__cell--header');
        expect(columns).toHaveLength(6);
        expect(columns[4].text()).toBe('sw-order.documentCard.labelAttach');
    });

    it('should show card filter when order has document', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();
        expect(wrapper.find('.sw-card-filter').exists()).toBeFalsy();

        await wrapper.setProps({
            order: {
                documents: getCollection('document', [
                    documentFixture,
                ]),
            },
        });

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        expect(wrapper.find('.sw-card-filter').exists()).toBeTruthy();
    });

    it('should change sent status when click on "Mark as unsent" context menu', async () => {
        global.activeAclRoles = [];

        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeTruthy();

        // Mark as sent option is disabled
        const markSentButton = wrapper.find('.sw-order-document-card__context-button-mark-sent');
        expect(markSentButton.attributes('disabled')).toBe('true');

        // Mark as unsent
        const markUnsentButton = wrapper.find('.sw-order-document-card__context-button-mark-unsent');
        await markUnsentButton.trigger('click');

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeFalsy();
        expect(markUnsentButton.attributes('disabled')).toBe('true');
    });

    it('should change sent status when click on "Mark as sent" context menu', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    sent: false,
                },
            ]),
        });

        const spyMarkDocumentAsSent = jest.spyOn(wrapper.vm, 'markDocumentAsSent');

        expect(wrapper.findComponent('.sw-data-grid-column-boolean').props('value')).toBeFalsy();

        // Mark as unsent option is disabled
        const markUnsentButton = wrapper.find('.sw-order-document-card__context-button-mark-unsent');
        expect(markUnsentButton.attributes('disabled')).toBe('true');

        // Mark as unsent
        const markSentButton = wrapper.find('.sw-order-document-card__context-button-mark-sent');
        await markSentButton.trigger('click');

        expect(spyMarkDocumentAsSent).toHaveBeenCalledTimes(1);
        expect(markSentButton.attributes('disabled')).toBe('true');
    });

    it('should show Send mail modal when choosing option Create and send in Create document modal', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
            showModal: true,
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();

        await wrapper.find('.sw-order-document-settings-invoice-modal__document-number input').setValue('1000');
        expect(wrapper.find('.sw-order-document-settings-invoice-modal__document-number input').element.value).toBe('1000');

        await wrapper.find('.sw-order-document-settings-modal__send-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('sw-order-send-document-modal-stub').exists()).toBeTruthy();
    });

    it('should call downloadDocument method when choosing option Create and download in Create document modal', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        wrapper.vm.downloadDocument = jest.fn();

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: {
                    name: 'Invoice',
                },
            },
            showModal: true,
        });

        expect(wrapper.find('.sw-modal[title="sw-order.documentModal.modalTitle - Invoice"]').exists()).toBeTruthy();

        await wrapper.find('.sw-order-document-settings-invoice-modal__document-number input').setValue('1000');
        expect(wrapper.find('.sw-order-document-settings-invoice-modal__document-number input').element.value).toBe('1000');

        await wrapper.find('.sw-order-document-settings-modal__download-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.downloadDocument).toHaveBeenCalled();
        wrapper.vm.downloadDocument.mockRestore();
    });

    it.each([
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_INVOICE,
            inputSelector: '.sw-order-document-settings-invoice-modal__document-number input',
            invoice: false,
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            inputSelector: '.sw-order-document-settings-storno-modal__document-number input',
            invoice: true,
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            inputSelector: '.sw-order-document-settings-credit-note-modal__document-number input',
            invoice: true,
        },
    ])(
        'should call downloadDocument with xml fileType for $technicalName',
        async ({ technicalName, inputSelector, invoice }) => {
            global.activeAclRoles = ['order.editor'];

            wrapper = await createWrapper({
                ...defaultProps,
                order: {
                    ...orderFixture,
                    documents: [
                        {
                            documentType: {
                                technicalName: DOCUMENT_TYPES.INVOICE,
                            },
                            config: {
                                custom: {
                                    documentNumber: '1000',
                                },
                            },
                        },
                    ],
                },
            });

            const downloadDocumentSpy = jest.spyOn(wrapper.vm, 'downloadDocument').mockImplementation(() => {});

            await wrapper.setData({
                currentDocumentType: {
                    id: '5',
                    technicalName,
                    name: 'test',
                    translated: { name: 'test' },
                },
                showModal: true,
            });

            await flushPromises();
            await wrapper.find(inputSelector).setValue('1000');

            if (invoice) {
                await wrapper.find('.mt-select__selection').trigger('click');
                await wrapper.find('.mt-select-result-list .mt-select-result').trigger('click');
            }

            await wrapper.find('.sw-order-document-settings-modal__download-button').trigger('click');
            await flushPromises();

            expect(downloadDocumentSpy).toHaveBeenCalledWith(expect.any(String), expect.any(String), 'xml');
            downloadDocumentSpy.mockRestore();
        },
    );

    it('should call downloadDocument with pdf fileType for regular invoice', async () => {
        global.activeAclRoles = ['order.editor'];
        wrapper = await createWrapper();

        const downloadDocumentSpy = jest.spyOn(wrapper.vm, 'downloadDocument').mockImplementation(() => {});

        await wrapper.setData({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
                translated: { name: 'Invoice' },
            },
            showModal: true,
        });

        await wrapper.find('.sw-order-document-settings-invoice-modal__document-number input').setValue('1000');
        await wrapper.find('.sw-order-document-settings-modal__download-button').trigger('click');
        await flushPromises();

        expect(downloadDocumentSpy).toHaveBeenCalledWith(expect.any(String), expect.any(String), 'pdf');
        downloadDocumentSpy.mockRestore();
    });

    it('should show permission tooltip message on Create document button correctly', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        const buttonCreate = wrapper.find('.sw-order-document-grid-button');
        expect(buttonCreate.attributes('tooltip-message')).toBe('sw-privileges.tooltip.warning');
        expect(buttonCreate.attributes('disabled')).toBeDefined();
    });

    it('should show order unsaved tooltip message on Create document button correctly', async () => {
        global.activeAclRoles = [
            'order.editor',
            'document.viewer',
        ];
        wrapper = await createWrapper();

        Shopware.Store.get('swOrderDetail').editing = true;
        await wrapper.vm.$nextTick();

        const buttonCreate = wrapper.findComponent('.sw-order-document-grid-button');
        expect(buttonCreate.attributes()['tooltip-message']).toBe('sw-order.documentTab.tooltipSaveBeforeCreateDocument');
        expect(buttonCreate.attributes('disabled')).toBeDefined();
    });

    it('should search documents with criteria queries', async () => {
        global.activeAclRoles = [];
        wrapper = await createWrapper();

        expect(wrapper.vm.documentCriteria.term).toBeNull();
        expect(wrapper.vm.documentCriteria.queries).toEqual([]);

        await wrapper.setData({
            term: '1000',
        });

        expect(wrapper.vm.documentCriteria.term).toBe('1000');
        expect(wrapper.vm.documentCriteria.queries).toEqual([
            {
                score: 500,
                query: {
                    type: 'contains',
                    field: 'config.documentDate',
                    value: '1000',
                },
            },
            {
                score: 500,
                query: {
                    type: 'equals',
                    field: 'config.documentNumber',
                    value: '1000',
                },
            },
        ]);
    });

    it('should render the only pdf on available formats column', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                { ...documentFixture, documentMediaFile: { fileExtension: 'pdf' }, documentA11yMediaFile: null },
            ]),
        });

        await flushPromises();

        const row = wrapper.find('.sw-data-grid__row--0');
        const fileTypes = row.find('.sw-data-grid__cell--fileTypes');

        expect(fileTypes.text()).toBe('PDF');
    });

    it('should render html and pdf on available formats column', async () => {
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                {
                    ...documentFixture,
                    documentMediaFile: {
                        fileExtension: 'pdf',
                    },
                    documentA11yMediaFile: {
                        fileExtension: 'html',
                    },
                },
            ]),
        });

        await flushPromises();

        const row = wrapper.find('.sw-data-grid__row--0');
        const fileTypes = row.find('.sw-data-grid__cell--fileTypes');

        expect(fileTypes.text()).toBe('PDF, HTML');
    });

    it('should render the delete-button when attachView is false', async () => {
        global.activeAclRoles = ['document.deleter'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        const deleteButton = wrapper.find(buttonDeleteClassDocumentCard);
        expect(deleteButton.exists()).toBe(true);
        expect(deleteButton.attributes('disabled')).toBe('false');
    });

    it('should disable the delete-button when attachView is true', async () => {
        global.activeAclRoles = ['document.deleter'];
        wrapper = await createWrapper(
            {
                ...defaultProps,
                attachView: true,
            },
            'sw.order.detail.documents',
        );

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        const deleteButton = wrapper.find(buttonDeleteClassEntityListing);
        expect(deleteButton.exists()).toBe(true);
        expect(deleteButton.attributes('disabled')).toBe('true');
    });

    it('should have a disabled delete-button with missing permissions', async () => {
        global.activeAclRoles = ['document.viewer'];
        wrapper = await createWrapper(defaultProps, 'sw.order.detail.documents');

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        const deleteButton = wrapper.find(buttonDeleteClassDocumentCard);
        expect(deleteButton.exists()).toBe(true);
        expect(deleteButton.attributes('disabled')).toBe('true');
    });

    it('should open the delete confirmation modal when delete button was clicked', async () => {
        global.activeAclRoles = ['document.deleter'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        expect(wrapper.find('.sw-modal').exists()).toBe(false);

        await wrapper.find(buttonDeleteClassDocumentCard).trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.find('.mt-banner--attention').exists()).toBe(true);

        const message = wrapper.find('.mt-banner__message');
        expect(message.exists()).toBe(true);
        expect(message.text()).toBe('sw-order.documentCard.confirmDeleteText');

        expect(wrapper.find('.mt-button--secondary').exists()).toBe(true);
        expect(wrapper.find('.mt-button--critical').exists()).toBe(true);
    });

    it('should remove the document from the list when delete was successful', async () => {
        global.activeAclRoles = ['document.deleter'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        documentSearchMock.mockResolvedValue(getCollection('document', []));

        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(1);

        await wrapper.find(buttonDeleteClassDocumentCard).trigger('click');

        await flushPromises();

        await wrapper.find('.mt-button--critical').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBe(false);
        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(0);
    });

    it('should not remove the document from the list when delete return an exception', async () => {
        global.activeAclRoles = ['document.viewer'];
        wrapper = await createWrapper();

        await wrapper.setData({
            documents: getCollection('document', [
                documentFixture,
            ]),
        });

        documentDeleteMock.mockRejectedValue({
            response: {
                data: {
                    errors: [
                        {
                            status: '422',
                            code: 'ERROR_CODE',
                            detail: 'Detailed error message',
                            title: 'Error Title',
                        },
                    ],
                },
            },
        });

        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(1);

        await wrapper.find(buttonDeleteClassDocumentCard).trigger('click');

        await flushPromises();

        await wrapper.find('.mt-button--critical').trigger('click');

        await flushPromises();

        expect(wrapper.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(1);
    });

    it.each([
        {
            technicalName: DOCUMENT_TYPES.INVOICE,
            expectedSelector: '.sw-order-document-settings-invoice-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.DELIVERY_NOTE,
            expectedSelector: 'sw-order-document-settings-delivery-note-modal-stub',
        },
        {
            technicalName: DOCUMENT_TYPES.CREDIT_NOTE,
            expectedSelector: '.sw-order-document-settings-credit-note-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.CANCELLATION_INVOICE,
            expectedSelector: '.sw-order-document-settings-storno-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_INVOICE,
            expectedSelector: '.sw-order-document-settings-invoice-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_EMBEDDED_INVOICE,
            expectedSelector: '.sw-order-document-settings-invoice-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_CANCELLATION_INVOICE,
            expectedSelector: '.sw-order-document-settings-storno-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_CREDIT_NOTE,
            expectedSelector: '.sw-order-document-settings-credit-note-modal__document-number',
        },
        {
            technicalName: DOCUMENT_TYPES.ZUGFERD_EMBEDDED_CREDIT_NOTE,
            expectedSelector: '.sw-order-document-settings-credit-note-modal__document-number',
        },
    ])('should render correct modal type for $technicalName', async ({ technicalName, expectedSelector }) => {
        wrapper = await createWrapper();

        await wrapper.setData({
            currentDocumentType: {
                id: '5',
                technicalName,
                name: 'test',
                translated: { name: 'test' },
            },
            showModal: true,
        });

        await flushPromises();

        expect(wrapper.find(expectedSelector).exists()).toBe(true);
    });
});
