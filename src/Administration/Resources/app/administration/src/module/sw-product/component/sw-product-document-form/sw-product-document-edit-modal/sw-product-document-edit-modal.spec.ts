/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { toRaw, type ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { DocumentChanges, DocumentVariantOperation } from '../../../service/product-document-variant.service';
import { setDocumentMedia } from '../product-document.helper';
import type { DocumentUploadRules } from './index';

const mockNotifyError = jest.fn();

jest.mock('src/app/composables/use-notification', () => ({
    __esModule: true,
    default: () => ({ createNotificationError: mockNotifyError }),
}));

type ValidateMediaArgs = [Entity<'media'>, Entity<'product_document'>];
type ComponentWithProps = ComponentPublicInstance<Record<string, unknown>>;

const GERMAN_LANGUAGE_ID = repositoryFactory.create('language').create().id;
const uploadRules: DocumentUploadRules = {
    allowedExtensions: ['pdf'],
    allowedMimeTypesByExtension: { pdf: ['application/pdf'] },
    maxFileSize: 20 * 1024 * 1024,
    fileAccept: 'application/pdf',
};
const variantSync = jest.fn<Promise<string[]>, [string, DocumentVariantOperation[], boolean?]>();
const mediaRepository = { get: jest.fn<Promise<Entity<'media'> | null>, [string, unknown]>() };
const applySuccess = (count: number) => translate('sw-product.documentForm.applySuccess', { name: 'Handbook', count });

let wrapper: VueWrapper | undefined;
let savedMedia: Entity<'media'>;

function translate(key: string, values?: Record<string, unknown>): string {
    return values ? `${key} ${JSON.stringify(values, Object.keys(values).sort())}` : key;
}

function createMedia(fileName: string): Entity<'media'> {
    const media = repositoryFactory.create('media').create();
    Object.assign(media, { fileName, fileExtension: 'pdf', fileSize: 1024, private: false });
    return media;
}

function createDocument(title: string | null = 'Manual', media: Entity<'media'> | null = savedMedia) {
    const document = repositoryFactory.create('product_document').create();
    const translated = { title: title ?? 'Manual', mediaId: media?.id ?? savedMedia.id };
    document.productId = Shopware.Store.get('swProductDetail').product.id;
    Object.assign(document, { title, mediaId: media?.id ?? null, translated });
    setDocumentMedia(document, media ?? savedMedia);
    return document;
}

function loadProduct(id: EntityKey<'product'> | null = null) {
    const product = repositoryFactory.create('product').create(Shopware.Context.api, id);
    Object.assign(Shopware.Store.get('swProductDetail'), { product });
    return product;
}

function holdDryRun() {
    let resolveDryRun: (variantIds: string[]) => void = () => {};
    variantSync.mockReturnValueOnce(
        new Promise((resolve) => {
            resolveDryRun = resolve;
        }),
    );
    return (variantIds: string[]) => resolveDryRun(variantIds);
}

async function createWrapper({ hasVariants = false, ...props }: Record<string, unknown>) {
    Shopware.Store.get('swProductDetail').product.childCount = hasVariants ? 1 : 0;
    wrapper = shallowMount(await wrapTestComponent('sw-product-document-edit-modal', { sync: true }), {
        props: {
            languageName: 'Deutsch',
            uploadRules,
            uploadHint: 'PDF, up to 20 MB',
            titlePlaceholder: 'Manual',
            validateMedia: jest.fn(() => true),
            mediaFolderId: 'media-folder',
            canEdit: true,
            ...props,
        },
        global: {
            mocks: { $t: translate },
            provide: {
                productDocumentVariantService: { sync: variantSync },
                repositoryFactory: {
                    create: (name: keyof EntitySchema.EntityKeys) =>
                        name === 'media' ? mediaRepository : repositoryFactory.create(name),
                },
            },
            stubs: {
                'mt-modal-root': { name: 'ModalRootStub', emits: ['change'], template: '<div><slot /></div>' },
                'mt-modal': {
                    props: ['title'],
                    template: '<section><h2>{{ title }}</h2><slot /><slot name="footer" /></section>',
                },
                'mt-icon': true,
                'mt-base-field': false,
                'mt-field-error': false,
                'sw-upload-listener': true,
                'sw-media-upload-v2': {
                    props: { source: null, uploadTag: null, required: null, disabled: null },
                    template: '<div class="sw-media-upload-v2" />',
                },
                'sw-field-error': { props: ['error'], template: '<div />' },
                'sw-media-modal-v2': true,
            },
        },
    });
    return wrapper;
}

function getButton(modal: VueWrapper, text: string) {
    const button = modal.findAll('button').find((item) => item.text() === text);
    if (!button) {
        throw new Error(`Button not found: ${text}`);
    }
    return button;
}

function getTitleField(modal: VueWrapper) {
    return modal.getComponent<ComponentWithProps>('.sw-product-document-form__name-input');
}

function getTitleInput(modal: VueWrapper) {
    return modal.get<HTMLInputElement>('.sw-product-document-form__name-input input');
}

function getUpload(modal: VueWrapper) {
    return modal.getComponent<ComponentWithProps>('.sw-media-upload-v2');
}

function getShownFile(modal: VueWrapper) {
    const source = getUpload(modal).props('source') as Entity<'media'> | undefined;
    return source && toRaw(source);
}

function getApplyToVariants(modal: VueWrapper) {
    return modal.get<HTMLInputElement>('.sw-product-document-form__apply-to-variants input');
}

function getValidatedMedia(validateMedia: jest.Mock<boolean, ValidateMediaArgs>) {
    return validateMedia.mock.calls.map(([media, exceptDocument]) => [media, toRaw(exceptDocument)]);
}

function appliedEdits(modal: VueWrapper) {
    return modal
        .emitted<[{ changes: DocumentChanges; media: Entity<'media'> | null }]>('apply')
        ?.map(([{ changes, media }]) => ({ changes, media: media && toRaw(media) }));
}

function variantResults(modal: VueWrapper) {
    return modal
        .emitted<[{ operation: DocumentVariantOperation | null; message: string | null }]>('apply')
        ?.map(([{ operation, message }]) => ({ operation, message }));
}

async function apply(modal: VueWrapper) {
    await getButton(modal, 'global.default.apply').trigger('click');
    await flushPromises();
}

function dismiss(modal: VueWrapper, isOpen = false) {
    modal.getComponent<ComponentPublicInstance>({ name: 'ModalRootStub' }).vm.$emit('change', isOpen);
}

async function openMediaLibrary(modal: VueWrapper) {
    getUpload(modal).vm.$emit('media-upload-sidebar-open');
    await flushPromises();
}

async function selectFromMediaLibrary(modal: VueWrapper, media: Entity<'media'>[]) {
    await openMediaLibrary(modal);
    modal.getComponent<ComponentPublicInstance>('sw-media-modal-v2-stub').vm.$emit('media-modal-selection-change', media);
    await flushPromises();
}

async function removeFile(modal: VueWrapper) {
    getUpload(modal).vm.$emit('media-upload-remove-image');
    await flushPromises();
}

describe('sw-product-document-edit-modal', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    let product: Entity<'product'>;
    let document: Entity<'product_document'>;

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        Shopware.Store.get('swProductDetail').$reset();
        product = loadProduct();
        savedMedia = createMedia('manual');
        document = createDocument();
        variantSync.mockReset().mockResolvedValue([]);
        mediaRepository.get.mockReset();
        mockNotifyError.mockClear();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        Shopware.Context.api.languageId = originalLanguageId;
    });

    describe('in the default language', () => {
        it('shows the saved title and file as required fields', async () => {
            const modal = await createWrapper({ document });

            expect(modal.get('h2').text()).toBe('sw-product.documentForm.editTitle');
            expect(getTitleInput(modal).element.value).toBe('Manual');
            expect(getTitleInput(modal).element.maxLength).toBe(255);
            expect(getTitleField(modal).props('required')).toBe(true);
            expect(getUpload(modal).props('required')).toBe(true);
            expect(getShownFile(modal)).toBe(savedMedia);
            expect(modal.text()).toContain('PDF, up to 20 MB');
            expect(modal.find('.sw-product-document-form__file-error').exists()).toBe(false);
            expect(modal.find('.sw-product-document-form__apply-to-variants').exists()).toBe(false);
        });

        it('reports a missing title and file only after Apply until both are provided again', async () => {
            const replacement = createMedia('handbook');
            const modal = await createWrapper({ document });
            await getTitleInput(modal).setValue('   ');
            await removeFile(modal);

            expect(getShownFile(modal)).toBeUndefined();
            expect(getTitleField(modal).props('error')).toBeNull();
            expect(modal.find('.sw-product-document-form__file-error').exists()).toBe(false);

            await apply(modal);

            expect(getTitleField(modal).props('error')).toEqual({
                code: 'PRODUCT_DOCUMENT_NAME_REQUIRED',
                detail: 'sw-product.documentForm.errorNameRequired',
            });
            const fileError = modal.getComponent<ComponentWithProps>('.sw-product-document-form__file-error');
            expect(fileError.props('error')).toEqual({
                code: 'PRODUCT_DOCUMENT_FILE_REQUIRED',
                detail: 'sw-product.documentForm.errorFileRequired',
            });
            expect(fileError.attributes('role')).toBe('alert');
            expect(modal.find('.sw-product-document-form__upload-hint').exists()).toBe(false);
            expect(modal.emitted('apply')).toBeUndefined();

            await getTitleInput(modal).setValue('Handbook');
            await selectFromMediaLibrary(modal, [replacement]);

            expect(getTitleField(modal).props('error')).toBeNull();
            expect(modal.find('.sw-product-document-form__file-error').exists()).toBe(false);

            await apply(modal);

            expect(appliedEdits(modal)).toEqual([
                { changes: { title: 'Handbook', mediaId: replacement.id }, media: replacement },
            ]);
        });

        it.each([
            { scenario: 'no change for the saved title', title: 'Manual', changes: {} },
            { scenario: 'no change for the saved title with spaces', title: '  Manual  ', changes: {} },
            { scenario: 'a trimmed new title', title: '  Handbook ', changes: { title: 'Handbook' } },
            { scenario: 'a title with 255 characters', title: 'a'.repeat(255), changes: { title: 'a'.repeat(255) } },
        ])('applies $scenario without writing to the document', async ({ title, changes }) => {
            const modal = await createWrapper({ document });
            await getTitleInput(modal).setValue(title);
            await apply(modal);

            expect(appliedEdits(modal)).toEqual([{ changes, media: null }]);
            expect(document).toMatchObject({ title: 'Manual', mediaId: savedMedia.id });
        });

        it('replaces the file only with a valid media library selection once the folder is known', async () => {
            const rejected = createMedia('rejected');
            const replacement = createMedia('handbook');
            const validateMedia = jest.fn<boolean, ValidateMediaArgs>((media) => media !== rejected);
            const modal = await createWrapper({ document, validateMedia, mediaFolderId: undefined });

            await openMediaLibrary(modal);
            expect(modal.find('sw-media-modal-v2-stub').exists()).toBe(false);

            await modal.setProps({ mediaFolderId: 'media-folder' });
            await openMediaLibrary(modal);
            expect(modal.get('sw-media-modal-v2-stub').attributes()).toMatchObject({
                'initial-folder-id': 'media-folder',
                'file-accept': 'application/pdf',
            });

            await selectFromMediaLibrary(modal, [rejected]);
            expect(getShownFile(modal)).toBe(savedMedia);

            await selectFromMediaLibrary(modal, [replacement]);
            expect(getValidatedMedia(validateMedia)).toEqual([
                [rejected, document],
                [replacement, document],
            ]);
            expect(modal.find('sw-media-modal-v2-stub').exists()).toBe(false);
            expect(getShownFile(modal)).toBe(replacement);

            await apply(modal);

            expect(appliedEdits(modal)).toEqual([{ changes: { mediaId: replacement.id }, media: replacement }]);
            expect(document.mediaId).toBe(savedMedia.id);
        });

        it('replaces the file with a validated upload and reports uploads that cannot be loaded', async () => {
            const uploaded = createMedia('uploaded');
            const validateMedia = jest.fn<boolean, ValidateMediaArgs>(() => true);
            const modal = await createWrapper({ document, validateMedia });
            const listener = modal.getComponent<ComponentPublicInstance>('sw-upload-listener-stub');

            expect(listener.attributes('upload-tag')).toMatch(/^sw-product-document-edit-modal-/);
            expect(listener.attributes('upload-tag')).toBe(getUpload(modal).props('uploadTag'));

            mediaRepository.get.mockRejectedValueOnce(new Error('Not found'));
            listener.vm.$emit('media-upload-finish', { targetId: 'missing-media' });
            await flushPromises();

            expect(mockNotifyError).toHaveBeenCalledWith({ message: 'sw-product.documentForm.errorLoadMedia' });
            expect(getShownFile(modal)).toBe(savedMedia);

            mediaRepository.get.mockResolvedValueOnce(uploaded);
            listener.vm.$emit('media-upload-finish', { targetId: uploaded.id });
            await flushPromises();

            expect(mediaRepository.get).toHaveBeenLastCalledWith(uploaded.id, Shopware.Context.api);
            expect(getValidatedMedia(validateMedia)).toEqual([[uploaded, document]]);
            expect(getShownFile(modal)).toBe(uploaded);

            await apply(modal);

            expect(appliedEdits(modal)).toEqual([{ changes: { mediaId: uploaded.id }, media: uploaded }]);
        });

        it('disables editing without edit permission but still closes on Cancel and dismiss', async () => {
            const modal = await createWrapper({ document, canEdit: false, hasVariants: true });

            expect(getTitleInput(modal).element.disabled).toBe(true);
            expect(getUpload(modal).props('disabled')).toBe(true);
            expect(getApplyToVariants(modal).element.disabled).toBe(true);
            expect(getButton(modal, 'global.default.apply').attributes('disabled')).toBeDefined();

            await apply(modal);
            await getButton(modal, 'global.default.cancel').trigger('click');
            dismiss(modal, true);
            dismiss(modal);

            expect(modal.emitted('apply')).toBeUndefined();
            expect(variantSync).not.toHaveBeenCalled();
            expect(modal.emitted('close')).toHaveLength(2);
        });
    });

    describe('for a language version', () => {
        beforeEach(() => {
            Shopware.Context.api.languageId = GERMAN_LANGUAGE_ID;
        });

        it('adds a language version whose optional title and file fall back to the default language', async () => {
            const germanMedia = createMedia('handbuch');
            const modal = await createWrapper({ document: createDocument(null, null) });

            expect(modal.get('h2').text()).toBe(
                translate('sw-product.documentForm.addLanguageVersion', { language: 'Deutsch' }),
            );
            expect(getTitleInput(modal).element.value).toBe('');
            expect(getTitleInput(modal).attributes('placeholder')).toBe('Manual');
            expect(getTitleField(modal).props('required')).toBe(false);
            expect(getUpload(modal).props('required')).toBe(false);
            expect(getShownFile(modal)).toBeUndefined();
            expect(modal.text()).toContain('sw-product.documentForm.fileFallbackHint');

            await apply(modal);

            expect(modal.find('.sw-product-document-form__file-error').exists()).toBe(false);
            expect(appliedEdits(modal)).toEqual([{ changes: {}, media: null }]);

            await getTitleInput(modal).setValue('Manual');
            await selectFromMediaLibrary(modal, [germanMedia]);
            await apply(modal);

            const changes = { title: 'Manual', mediaId: germanMedia.id };
            expect(appliedEdits(modal)?.[1]).toEqual({ changes, media: germanMedia });
        });

        it('edits an existing language version and clears its own title and file', async () => {
            const germanMedia = createMedia('handbuch');
            const modal = await createWrapper({ document: createDocument('Handbuch', germanMedia) });

            expect(modal.get('h2').text()).toBe('sw-product.documentForm.editTitle');
            expect(getTitleInput(modal).element.value).toBe('Handbuch');
            expect(getShownFile(modal)).toBe(germanMedia);

            await getTitleInput(modal).setValue('');
            await removeFile(modal);
            await apply(modal);

            expect(appliedEdits(modal)).toEqual([{ changes: { title: null, mediaId: null }, media: null }]);
        });
    });

    describe('applying to variants', () => {
        it('dry-runs only real changes against the saved values and emits them with the variant count', async () => {
            const replacement = createMedia('handbook');
            const modal = await createWrapper({ document, hasVariants: true });

            expect(getApplyToVariants(modal).element.checked).toBe(true);
            await apply(modal);

            expect(variantSync).not.toHaveBeenCalled();
            expect(appliedEdits(modal)).toEqual([{ changes: {}, media: null }]);
            expect(variantResults(modal)).toEqual([{ operation: null, message: null }]);

            variantSync.mockResolvedValueOnce(['variant-a', 'variant-b']);
            await getTitleInput(modal).setValue('Handbook');
            await selectFromMediaLibrary(modal, [replacement]);
            await apply(modal);

            const changes = { title: 'Handbook', mediaId: replacement.id };
            const operation = { type: 'update', mediaId: savedMedia.id, title: 'Manual', changes };
            expect(variantSync.mock.calls).toEqual([[product.id, [operation], true]]);
            expect(appliedEdits(modal)?.[1]).toEqual({ changes, media: replacement });
            expect(variantResults(modal)?.[1]).toEqual({ operation, message: applySuccess(2) });
        });

        it('merges the edit into the operation already queued for the document', async () => {
            const saved = { type: 'update' as const, mediaId: 'old-media', title: 'Old' };
            const queued = { ...saved, changes: { title: 'Manual', mediaId: savedMedia.id } };
            Shopware.Store.get('swProductDetail').documentVariantOperations = { [document.id]: queued };
            const modal = await createWrapper({ document, hasVariants: true });
            await getTitleInput(modal).setValue('Handbook');
            await apply(modal);

            const operation = { ...saved, changes: { title: 'Handbook', mediaId: savedMedia.id } };
            expect(variantSync).toHaveBeenCalledWith(product.id, [operation], true);
            expect(appliedEdits(modal)).toEqual([{ changes: { title: 'Handbook' }, media: null }]);
            expect(variantResults(modal)).toEqual([{ operation, message: applySuccess(0) }]);
            expect(Shopware.Store.get('swProductDetail').documentVariantOperations).toEqual({ [document.id]: queued });
        });

        it('leaves the variants untouched when applying to variants is unchecked', async () => {
            const modal = await createWrapper({ document, hasVariants: true });
            await getApplyToVariants(modal).setValue(false);
            await getTitleInput(modal).setValue('Handbook');
            await apply(modal);

            expect(variantSync).not.toHaveBeenCalled();
            expect(appliedEdits(modal)).toEqual([{ changes: { title: 'Handbook' }, media: null }]);
            expect(variantResults(modal)).toEqual([{ operation: null, message: null }]);
        });

        it.each([
            { scenario: 'another product', isSameProduct: false },
            { scenario: 'a reload of the same product', isSameProduct: true },
        ])('ignores close and apply during the dry run and drops the result after $scenario', async ({ isSameProduct }) => {
            const finishDryRun = holdDryRun();
            const modal = await createWrapper({ document, hasVariants: true });
            await getTitleInput(modal).setValue('Handbook');
            await apply(modal);

            expect(getButton(modal, 'global.default.cancel').attributes('disabled')).toBeDefined();
            expect(getButton(modal, 'global.default.apply').attributes('disabled')).toBeDefined();
            expect(getTitleInput(modal).element.disabled).toBe(true);

            await getButton(modal, 'global.default.cancel').trigger('click');
            dismiss(modal);
            await apply(modal);

            expect(modal.emitted('close')).toBeUndefined();
            expect(modal.emitted('apply')).toBeUndefined();
            expect(variantSync).toHaveBeenCalledTimes(1);

            loadProduct(isSameProduct ? product.id : null);
            finishDryRun(['variant-a']);
            await flushPromises();

            expect(modal.emitted('apply')).toBeUndefined();
        });

        it('shows an error and keeps the modal open when the dry run fails', async () => {
            variantSync.mockRejectedValueOnce(new Error('Server error'));
            const modal = await createWrapper({ document, hasVariants: true });
            await getTitleInput(modal).setValue('Handbook');
            await apply(modal);

            expect(mockNotifyError).toHaveBeenCalledWith({ message: 'sw-product.documentForm.errorLoadVariants' });
            expect(modal.emitted('apply')).toBeUndefined();
            expect(getButton(modal, 'global.default.cancel').attributes('disabled')).toBeUndefined();
            expect(getButton(modal, 'global.default.apply').attributes('disabled')).toBeUndefined();

            variantSync.mockResolvedValueOnce(['variant-a']);
            await apply(modal);

            expect(appliedEdits(modal)).toEqual([{ changes: { title: 'Handbook' }, media: null }]);
        });
    });
});
