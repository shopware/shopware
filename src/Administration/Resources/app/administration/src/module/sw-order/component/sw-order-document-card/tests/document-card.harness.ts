import { mount, type VueWrapper } from '@vue/test-utils';
import { DocumentEvents, EntityCollection, useOrderDetailStore } from '../dependencies';

/**
 * @sw-package after-sales
 */

/**
 * An action in the menu of a document row: a dropdown menu with document generation v2, a context menu before.
 *
 * @private
 */
export type DocumentAction = 'open' | 'download' | 'download-all' | 'send' | 'mark-sent' | 'mark-unsent' | 'delete';

/**
 * A request the card sends to the document repository, as the fake repository received it.
 *
 * @private
 */
export type DocumentSearch = { term: string | null; queries: unknown[]; filters: unknown[]; page: number | null };

/**
 * A file the card handed to the browser, through a link it clicked.
 *
 * @private
 */
export type OpenedFile = { url: string; fileName: string; inNewTab: boolean };

/**
 * The buttons that start creating a document: in the card filter while documents exist, in the empty state otherwise.
 *
 * @private
 */
export const BUTTON_CREATE_DOCUMENT = '.sw-order-document-grid-button';

/**
 * The context menu item next to the create button that opens the upload modal instead.
 *
 * @private
 */
export const BUTTON_UPLOAD_DOCUMENT = '.sw-order-document-grid-button-upload';

/**
 * The modals the card opens. The harness renders each as an empty stub that a test lets emit the real events.
 *
 * @private
 */
export const MODALS = {
    createDocument: 'sw-order-create-document-modal',
    uploadDocument: 'sw-order-upload-document-modal',
    selectDocumentType: 'sw-order-select-document-type-modal',
    sendDocument: 'sw-order-send-document-modal',
    /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
    legacyDocumentSettings: 'sw-order-document-settings-modal',
    /** @deprecated tag:v6.9.0 - Removed with document generation v1. */
    legacyInvoiceSettings: 'sw-order-document-settings-invoice-modal',
} as const;

const ACTION_SELECTORS: Record<DocumentAction, string> = {
    open: '.sw-order-document-card__context-button-open-pdf',
    download: '.sw-order-document-card__context-button-download-pdf',
    'download-all': '.sw-order-document-card__context-button-download-all-formats',
    send: '.sw-order-document-card__context-button-send',
    'mark-sent': '.sw-order-document-card__context-button-mark-sent',
    'mark-unsent': '.sw-order-document-card__context-button-mark-unsent',
    delete: '.sw-order-document-card__context-button-delete',
};

const FORMAT_SELECTORS: Partial<Record<DocumentAction, string>> = {
    open: '.sw-order-document-card__context-button-open-format',
    download: '.sw-order-document-card__context-button-download-format',
};

/** Renders the dropdown menus in place, so a test reaches their items without a teleport. */
const actionMenuStubs = {
    'mt-action-menu': { template: '<div><slot /></div>' },
    'mt-action-menu-group': { template: '<div><slot /></div>' },
    'mt-action-menu-item': {
        props: { disabled: { type: Boolean, required: false, default: false } },
        emits: ['select'],
        template: '<button type="button" :disabled="disabled" @click="$emit(\'select\', $event)"><slot /></button>',
    },
    'mt-dropdown-menu-root': { template: '<div><slot /></div>' },
    'mt-dropdown-menu-trigger': { template: '<div><slot /></div>' },
    'mt-dropdown-menu-portal': { template: '<div><slot /></div>' },
    'mt-dropdown-menu-sub': { template: '<div><slot /></div>' },
};

/** Lets a test type a search term without the debounce of the real card filter. */
const cardFilterStub = {
    name: 'sw-card-filter',
    emits: ['sw-card-filter-term-change'],
    template: '<div class="sw-card-filter"><slot name="filter" /></div>',
};

const modalStubs = Object.fromEntries(
    Object.values(MODALS).map((name) => [
        name,
        { name, inheritAttrs: false, template: `<div class="${name}-stub" />` },
    ]),
);

const documentTypes = [
    { id: 'invoiceTypeId', name: 'Invoice', technicalName: 'invoice', translated: { name: 'Invoice' } },
    {
        id: 'deliveryNoteTypeId',
        name: 'Delivery note',
        technicalName: 'delivery_note',
        translated: { name: 'Delivery note' },
    },
];

const wrappers: VueWrapper[] = [];
const restorers: Array<() => void> = [];
let storedDocuments: Array<Record<string, unknown>> = [];
let documentSearches: DocumentSearch[] = [];
let openedFiles: OpenedFile[] = [];
let notifications: Array<{ variant: string; message: string }> = [];
let legacyDocumentListener: ((event: { action: string; payload?: unknown }) => void) | null = null;

/** Wraps entities into the collection a repository search returns. */
function createCollection(entity: 'document' | 'document_type', items: Array<Record<string, unknown>>) {
    return new EntityCollection(
        `/${entity}`,
        entity,
        { isShopwareContext: true } as never,
        null,
        items as never,
        items.length,
        null,
    );
}

/** A document repository that keeps the documents of `setUpDocuments()`, like the server would. */
function createDocumentRepository() {
    return {
        search: jest.fn((criteria: Record<string, unknown>) => {
            documentSearches.push({
                term: criteria.term as string | null,
                queries: criteria.queries as unknown[],
                filters: criteria.filters as unknown[],
                page: criteria.page as number | null,
            });

            return Promise.resolve(createCollection('document', [...storedDocuments]));
        }),
        get: jest.fn((id: string) => Promise.resolve(storedDocuments.find((document) => document.id === id) ?? null)),
        save: jest.fn((document: Record<string, unknown>) => {
            storedDocuments = storedDocuments.map((stored) => (stored.id === document.id ? { ...document } : stored));

            return Promise.resolve();
        }),
        delete: jest.fn((id: string) => {
            storedDocuments = storedDocuments.filter((document) => document.id !== id);

            return Promise.resolve();
        }),
    };
}

/** Fakes of every service the card injects, with answers that let a document be created, opened and sent. */
function createServices() {
    return {
        documentRepository: createDocumentRepository(),
        documentTypeRepository: {
            search: jest.fn(() => Promise.resolve(createCollection('document_type', documentTypes))),
        },
        documentV2ApiService: {
            createDocument: jest.fn((orderId: string, documentType: string, formats: string[]) =>
                Promise.resolve({ documentId: 'createdDocumentId', documentNumber: null as string | null, formats }),
            ),
            uploadDocument: jest.fn<Promise<{ documentId: string }>, unknown[]>(() =>
                Promise.resolve({ documentId: 'uploadedDocumentId' }),
            ),
            previewDocument: jest.fn(() => Promise.resolve({ file: 'preview-file', fileName: 'preview.pdf' })),
            getDocument: jest.fn((documentId: string, fileType: string) =>
                Promise.resolve({ file: `${documentId}-file`, fileName: `${documentId}.${fileType}` }),
            ),
            getDocumentArchive: jest.fn(() => Promise.resolve({ file: 'archive-file', fileName: 'documents.zip' })),
        },
        documentV2Service: {
            getAvailableDocumentTypes: jest.fn(() => Promise.resolve({})),
            getDocumentTypeLabel: jest.fn(
                (technicalName: string, label?: Record<string, string>) => label?.['en-GB'] ?? `${technicalName}--label`,
            ),
            getPreferredFileFormat: jest.fn((formats: string[], fallback: string) => formats[0] ?? fallback),
            sortFileFormats: jest.fn((formats: string[]) => [...formats]),
            getFileFormatSnippet: jest.fn((format: string) => `${format}--snippet`),
            getErrorTranslation: jest.fn<string | null, [code: string, parameters: unknown]>(() => null),
        },
        documentService: {
            setListener: jest.fn((listener: typeof legacyDocumentListener) => {
                legacyDocumentListener = listener;
            }),
            createDocument: jest.fn(() =>
                Promise.resolve({ data: { documentId: 'createdDocumentId', documentDeepLink: 'deepLink' } }),
            ),
            getDocument: jest.fn(() =>
                Promise.resolve({
                    data: 'legacy-file',
                    headers: { 'content-disposition': 'attachment; filename=legacy.pdf' },
                }),
            ),
            getDocumentPreview: jest.fn(() => Promise.resolve({ data: 'legacy-preview' })),
        },
        numberRangeService: {
            reserve: jest.fn(() => Promise.resolve({ number: '1000' })),
        },
    };
}

let services = createServices();

/**
 * The fake services the card talks to, fresh for each test.
 * Read them to check what the card sent, or change one before mounting or acting, for example to let it fail.
 *
 * @private
 * @example
 * getServices().documentV2ApiService.createDocument.mockRejectedValueOnce(new Error());
 */
export function getServices() {
    return services;
}

/**
 * An order with the fields the card reads. `documents` decides whether the card offers a search before it loaded.
 *
 * @private
 */
export function createOrder(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id: 'orderId',
        versionId: 'orderVersionId',
        orderNumber: '10000',
        deepLinkCode: 'orderDeepLink',
        documents: [],
        lineItems: [],
        ...overrides,
    };
}

/**
 * A document of the order. Without overrides it is an unsent invoice that exists as PDF only.
 *
 * @private
 * @example
 * createDocument('documentA', { documentFiles: [{ documentFormat: 'pdf' }, { documentFormat: 'html' }] });
 */
export function createDocument(id: string, overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        id,
        orderId: 'orderId',
        sent: false,
        deepLinkCode: `${id}-deepLink`,
        createdAt: '2026-01-01T00:00:00.000Z',
        typeName: 'invoice',
        documentType: { id: 'invoiceTypeId', name: 'Invoice', technicalName: 'invoice', translated: { name: 'Invoice' } },
        config: { documentNumber: `${id}-number`, documentDate: '2026-01-01' },
        documentFiles: [{ documentFormat: 'pdf' }],
        documentMediaFile: null,
        documentA11yMediaFile: null,
        ...overrides,
    };
}

/**
 * Stores the documents the fake repository finds for the order. Saving and deleting change them like the server.
 *
 * @private
 */
export function setUpDocuments(documents: Array<Record<string, unknown>>): void {
    storedDocuments = documents;
}

/**
 * Returns the documents as the fake repository stores them now, after the card saved or deleted some.
 *
 * @private
 */
export function getStoredDocuments(): Array<Record<string, unknown>> {
    return storedDocuments;
}

/**
 * Returns every search the card sent to the document repository, oldest first.
 *
 * @private
 */
export function getDocumentSearches(): DocumentSearch[] {
    return documentSearches;
}

/**
 * Gives the logged-in user these privileges, for example `document.viewer`.
 *
 * @private
 */
export function grantPrivileges(...privileges: string[]): void {
    // The acl service mock of the jest setup reads this global, which TypeScript does not know.
    Reflect.set(globalThis, 'activeAclRoles', privileges);
}

/**
 * Puts the order detail page into edit mode, as when the merchant changed the order and did not save it yet.
 *
 * @private
 */
export function startEditingOrder(): void {
    useOrderDetailStore().editing = true;
}

/**
 * Mounts the document card on a page of the order detail; `routeName` decides which columns it shows.
 * Every modal is a stub, the files it opens and the notifications it shows are recorded.
 *
 * @private
 */
export async function mountDocumentCard(
    props: { order?: Record<string, unknown>; attachView?: boolean; isLoading?: boolean } = {},
    { routeName = 'sw.order.detail.documents' }: { routeName?: string } = {},
): Promise<VueWrapper> {
    recordOpenedFiles();
    recordNotifications();

    const wrapper = mount(await wrapTestComponent('sw-order-document-card', { sync: true }), {
        props: { order: createOrder(), ...props },
        global: {
            stubs: {
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-card-filter': cardFilterStub,
                ...actionMenuStubs,
                ...modalStubs,
            },
            provide: {
                documentService: services.documentService,
                documentV2ApiService: services.documentV2ApiService,
                documentV2Service: services.documentV2Service,
                numberRangeService: services.numberRangeService,
                repositoryFactory: {
                    create: (entity: string) =>
                        entity === 'document_type' ? services.documentTypeRepository : services.documentRepository,
                },
                searchRankingService: {},
            },
            mocks: {
                $t: (key: string, values?: Record<string, unknown>) => (values ? `${key} ${JSON.stringify(values)}` : key),
                // A query as the listing mixin puts into the URL, so the card loads its documents when it is created.
                $route: {
                    name: routeName,
                    query: { page: 1, limit: 25 },
                    meta: { $module: { icon: 'regular-shopping-bag' } },
                },
            },
            directives: {
                tooltip: {
                    mounted(element: HTMLElement, binding: { value: { message: string } }) {
                        element.setAttribute('data-tooltip', binding.value.message);
                    },
                    updated(element: HTMLElement, binding: { value: { message: string } }) {
                        element.setAttribute('data-tooltip', binding.value.message);
                    },
                },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper;
}

/** Records the links the card clicks to open or download a file, instead of letting the browser follow them. */
function recordOpenedFiles(): void {
    const originalCreateObjectURL = Object.getOwnPropertyDescriptor(URL, 'createObjectURL');
    URL.createObjectURL = ((file: unknown) => `blob:${String(file)}`) as typeof URL.createObjectURL;
    const dispatch = jest.spyOn(HTMLAnchorElement.prototype, 'dispatchEvent').mockImplementation(function record(
        this: HTMLAnchorElement,
    ) {
        openedFiles.push({ url: this.href, fileName: this.download, inNewTab: this.target === '_blank' });

        return true;
    });

    restorers.push(() => {
        if (originalCreateObjectURL) {
            Object.defineProperty(URL, 'createObjectURL', originalCreateObjectURL);
        }
        dispatch.mockRestore();
    });
}

/** Records the notifications the card shows, instead of adding them to the notification store. */
function recordNotifications(): void {
    const create = jest
        .spyOn(Shopware.Store.get('notification'), 'createNotification')
        .mockImplementation((notification) => {
            notifications.push({ variant: notification.variant ?? '', message: notification.message ?? '' });

            return Promise.resolve(null) as never;
        });

    restorers.push(() => create.mockRestore());
}

/**
 * Returns the files the card opened or downloaded, oldest first.
 *
 * @private
 */
export function getOpenedFiles(): OpenedFile[] {
    return openedFiles;
}

/**
 * Returns the notifications the card showed, oldest first.
 *
 * @private
 */
export function getNotifications(): Array<{ variant: string; message: string }> {
    return notifications;
}

/**
 * Types a search term into the card filter.
 *
 * @private
 */
export async function searchDocuments(wrapper: VueWrapper, term: string): Promise<void> {
    const cardFilter: VueWrapper = wrapper.findComponent({ name: 'sw-card-filter' });
    cardFilter.vm.$emit('sw-card-filter-term-change', term);
    await flushPromises();
}

/**
 * Clicks the first visible button that starts creating a document.
 *
 * @private
 */
export async function clickCreateDocument(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(BUTTON_CREATE_DOCUMENT).trigger('click');
    await flushPromises();
}

/**
 * Clicks the menu item next to the create button that opens the upload modal.
 *
 * @private
 */
export async function clickUploadDocument(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(BUTTON_UPLOAD_DOCUMENT).trigger('click');
    await flushPromises();
}

/**
 * Returns the names of the modals the card shows now, see `MODALS`.
 *
 * @private
 */
export function getOpenModals(wrapper: VueWrapper): string[] {
    return Object.values(MODALS).filter((name) => wrapper.findComponent({ name }).exists());
}

/**
 * Returns what the card passes to a modal it shows, by the attribute names of the template.
 *
 * @private
 * @example
 * expect(getModalProps(wrapper, MODALS.createDocument)['is-loading-document']).toBe(true);
 */
export function getModalProps(wrapper: VueWrapper, modal: string): Record<string, unknown> {
    const stub: VueWrapper = wrapper.findComponent({ name: modal });

    return stub.vm.$attrs;
}

/**
 * Lets a modal the card shows emit an event, as the real modal does when the merchant uses it.
 *
 * @private
 * @example
 * await emitFromModal(wrapper, MODALS.createDocument, 'update:documentType', { technicalName: 'invoice' });
 */
export async function emitFromModal(wrapper: VueWrapper, modal: string, event: string, ...args: unknown[]): Promise<void> {
    const stub: VueWrapper = wrapper.findComponent({ name: modal });
    stub.vm.$emit(event, ...args);
    await flushPromises();
}

/**
 * Creates a document of this type in the create modal of document generation v2, with the modal's parameters.
 * `additionalAction` is what the modal's split button asked for: `download`, `send` or nothing.
 *
 * @private
 */
export async function createDocumentInModal(
    wrapper: VueWrapper,
    technicalName: string,
    params: Record<string, unknown> = {},
    additionalAction = '',
): Promise<void> {
    await clickCreateDocument(wrapper);
    await emitFromModal(wrapper, MODALS.createDocument, 'update:documentType', { technicalName });
    await emitFromModal(wrapper, MODALS.createDocument, 'document-create', params, additionalAction);
}

/**
 * Uploads a file as a document of this type in the upload modal, with the modal's parameters.
 * `additionalAction` is what the modal's split button asked for: `send` or nothing.
 *
 * @private
 */
export async function uploadDocumentInModal(
    wrapper: VueWrapper,
    technicalName: string,
    params: Record<string, unknown> = {},
    { additionalAction = '', file = null }: { additionalAction?: string; file?: File | null } = {},
): Promise<void> {
    await clickUploadDocument(wrapper);
    await emitFromModal(wrapper, MODALS.uploadDocument, 'update:documentType', { technicalName });
    await emitFromModal(wrapper, MODALS.uploadDocument, 'document-upload', params, additionalAction, file);
}

/**
 * Chooses a document type in the type selection of document generation v1, which opens the settings modal of the type.
 *
 * @private
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 */
export async function chooseLegacyDocumentType(wrapper: VueWrapper, technicalName: string): Promise<void> {
    await clickCreateDocument(wrapper);
    await emitFromModal(wrapper, MODALS.selectDocumentType, 'update:value', { technicalName });
    await emitFromModal(wrapper, MODALS.selectDocumentType, 'modal-close', true);
}

/**
 * Lets the legacy document service report that creating a document finished, or failed with this error.
 *
 * @private
 * @deprecated tag:v6.9.0 - Removed with document generation v1.
 * @example
 * await reportLegacyDocumentCreation({ code: 'DOCUMENT__NUMBER_ALREADY_EXISTS', detail: 'Number exists' });
 */
export async function reportLegacyDocumentCreation(error?: Record<string, unknown>): Promise<void> {
    legacyDocumentListener?.(
        error ? { action: DocumentEvents.DOCUMENT_FAILED, payload: error } : { action: DocumentEvents.DOCUMENT_FINISHED },
    );
    await flushPromises();
}

/**
 * Returns the rows of the document grid.
 *
 * @private
 */
export function getDocumentRows(wrapper: VueWrapper) {
    return wrapper.findAll('.sw-data-grid__body .sw-data-grid__row');
}

/**
 * Returns the document numbers the grid shows, in order.
 *
 * @private
 */
export function getShownDocumentNumbers(wrapper: VueWrapper): string[] {
    return getDocumentRows(wrapper).map((row) => row.find('.sw-data-grid__cell--config-documentNumber').text());
}

/**
 * Returns the labels of the grid's columns, without the actions column.
 *
 * @private
 */
export function getColumnLabels(wrapper: VueWrapper): string[] {
    return wrapper.findAll('.sw-data-grid__cell--header.sw-data-grid__cell--property').map((cell) => cell.text());
}

/**
 * Finds an action of the document row at this position; the first is 0.
 * With `format`, it finds the item of that file format in the open or download submenu of document generation v2.
 *
 * @private
 */
export function findDocumentAction(wrapper: VueWrapper, position: number, action: DocumentAction, format?: string) {
    const row = getDocumentRows(wrapper)[position];
    const formatSelector = FORMAT_SELECTORS[action];

    if (format && formatSelector) {
        const items = row.findAll(formatSelector).filter((item) => item.text().includes(`"${format}--snippet"`));

        return items[0];
    }

    return row.find(ACTION_SELECTORS[action]);
}

/**
 * Chooses an action of the document row at this position, see `findDocumentAction()`.
 *
 * @private
 */
export async function chooseDocumentAction(
    wrapper: VueWrapper,
    position: number,
    action: DocumentAction,
    format?: string,
): Promise<void> {
    await findDocumentAction(wrapper, position, action, format).trigger('click');
    await flushPromises();
}

/**
 * Confirms the delete modal that the delete action opened.
 *
 * @private
 */
export async function confirmDeletion(wrapper: VueWrapper): Promise<void> {
    await wrapper.find('.sw-modal .mt-button--critical').trigger('click');
    await flushPromises();
}

/**
 * Cancels the delete modal that the delete action opened.
 *
 * @private
 */
export async function cancelDeletion(wrapper: VueWrapper): Promise<void> {
    await wrapper.find('.sw-modal .mt-button--secondary').trigger('click');
    await flushPromises();
}

/**
 * Unmounts every document card the tests mounted and resets the fakes, the privileges and the order's edit mode.
 *
 * @private
 */
export function unmountDocumentCards(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    restorers.splice(0).forEach((restore) => restore());
    storedDocuments = [];
    documentSearches = [];
    openedFiles = [];
    notifications = [];
    legacyDocumentListener = null;
    services = createServices();
    Reflect.set(globalThis, 'activeAclRoles', []);
    useOrderDetailStore().editing = false;
}
