/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { DocumentVariantOperation } from 'src/core/service/api/product-document-variant.api.service';

const mockCreateNotificationError = jest.fn();

jest.mock('src/app/composables/use-notification', () => ({
    __esModule: true,
    default: () => ({ createNotificationError: mockCreateNotificationError }),
}));

type ComponentWithProps = ComponentPublicInstance<Record<string, unknown>>;

const GERMAN_LANGUAGE_ID = repositoryFactory.create('language').create().id;
const variantSync = jest.fn<Promise<string[]>, [string, DocumentVariantOperation[], boolean?]>();

let wrapper: VueWrapper | undefined;

function translate(key: string, values?: Record<string, unknown>): string {
    return values ? `${key} ${JSON.stringify(values, Object.keys(values).sort())}` : key;
}

function createDocument(title: string, mediaId: string | null = repositoryFactory.create('media').create().id) {
    const document = repositoryFactory.create('product_document').create();
    Object.assign(document, {
        productId: Shopware.Store.get('swProductDetail').product.id,
        title,
        mediaId,
        translated: { title, mediaId },
    });
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

async function createWrapper(props: Record<string, unknown>) {
    wrapper = shallowMount(await wrapTestComponent('sw-product-document-remove-modal', { sync: true }), {
        props: { languageName: 'English', canEdit: true, ...props },
        global: {
            mocks: { $t: translate },
            provide: { productDocumentVariantService: { sync: variantSync } },
            stubs: {
                'mt-modal-root': { name: 'ModalRootStub', emits: ['change'], template: '<div><slot /></div>' },
                'mt-modal': {
                    props: ['title'],
                    template: '<section><h2>{{ title }}</h2><slot /><slot name="footer" /></section>',
                },
                'mt-base-field': false,
                'mt-field-error': false,
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

function getRemoveFromVariants(modal: VueWrapper) {
    return modal.get<HTMLInputElement>('.sw-product-document-form__remove-from-variants input');
}

async function confirmRemoval(modal: VueWrapper) {
    await modal.get('.sw-product-document-form__confirm-remove').trigger('click');
    await flushPromises();
}

function getVariantOperations() {
    return Shopware.Store.get('swProductDetail').documentVariantOperations;
}

function removals(modal: VueWrapper) {
    return modal
        .emitted<[{ operations: Record<string, DocumentVariantOperation>; variantCount: number }]>('remove')
        ?.map(([payload]) => payload);
}

describe('sw-product-document-remove-modal', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    let product: Entity<'product'>;

    beforeEach(() => {
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        Shopware.Store.get('swProductDetail').$reset();
        product = loadProduct();
        variantSync.mockReset();
        variantSync.mockResolvedValue([]);
        mockCreateNotificationError.mockClear();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
        Shopware.Context.api.languageId = originalLanguageId;
    });

    it.each([
        {
            scope: 'one document',
            languageId: null,
            languageName: 'English',
            documentCount: 1,
            title: 'removeTitle',
            description: translate('sw-product.documentForm.removeDescription'),
            variant: 'critical',
        },
        {
            scope: 'several documents',
            languageId: null,
            languageName: 'English',
            documentCount: 2,
            title: 'bulkRemoveTitle',
            description: translate('sw-product.documentForm.bulkRemoveDescription'),
            variant: 'critical',
        },
        {
            scope: 'the language version of one document',
            languageId: GERMAN_LANGUAGE_ID,
            languageName: 'Deutsch',
            documentCount: 1,
            title: 'removeLanguageVersionTitle',
            description: translate('sw-product.documentForm.removeLanguageVersionDescription', { language: 'Deutsch' }),
            variant: 'primary',
        },
        {
            scope: 'the language versions of several documents',
            languageId: GERMAN_LANGUAGE_ID,
            languageName: 'Deutsch',
            documentCount: 2,
            title: 'bulkRemoveLanguageVersionTitle',
            description: translate('sw-product.documentForm.removeLanguageVersionDescription', { language: 'Deutsch' }),
            variant: 'primary',
        },
    ])(
        'asks to remove $scope and removes only after confirmation',
        async ({ languageId, languageName, documentCount, title, description, variant }) => {
            if (languageId) {
                Shopware.Context.api.languageId = languageId;
            }
            const documents = ['Manual', 'Certificate'].slice(0, documentCount).map((name) => createDocument(name));
            const modal = await createWrapper({ documents, languageName });
            const values = { count: documentCount, language: languageName, name: 'Manual' };

            expect(modal.get('h2').text()).toBe(translate(`sw-product.documentForm.${title}`, values));
            expect(modal.text()).toContain(description);
            expect(
                modal.getComponent<ComponentWithProps>('.sw-product-document-form__confirm-remove').props('variant'),
            ).toBe(variant);
            expect(modal.find('.sw-product-document-form__remove-from-variants').exists()).toBe(false);
            expect(modal.emitted('remove')).toBeUndefined();

            await confirmRemoval(modal);

            expect(removals(modal)).toEqual([{ operations: {}, variantCount: 0 }]);
            expect(modal.emitted('close')).toBeUndefined();
            expect(variantSync).not.toHaveBeenCalled();
            expect(getVariantOperations()).toEqual({});
        },
    );

    it('closes on Cancel and when the modal is dismissed without removing', async () => {
        const modal = await createWrapper({ documents: [createDocument('Manual')] });

        await getButton(modal, 'global.default.cancel').trigger('click');
        modal.getComponent<ComponentPublicInstance>({ name: 'ModalRootStub' }).vm.$emit('change', true);
        modal.getComponent<ComponentPublicInstance>({ name: 'ModalRootStub' }).vm.$emit('change', false);

        expect(modal.emitted('close')).toHaveLength(2);
        expect(modal.emitted('remove')).toBeUndefined();
    });

    it('disables removal without edit permission but still allows closing', async () => {
        const modal = await createWrapper({ documents: [createDocument('Manual')], canEdit: false, hasVariants: true });

        expect(modal.get('.sw-product-document-form__confirm-remove').attributes('disabled')).toBeDefined();
        expect(getRemoveFromVariants(modal).element.disabled).toBe(true);

        await confirmRemoval(modal);
        await getButton(modal, 'global.default.cancel').trigger('click');

        expect(modal.emitted('remove')).toBeUndefined();
        expect(variantSync).not.toHaveBeenCalled();
        expect(modal.emitted('close')).toHaveLength(1);
    });

    describe('removing from variants', () => {
        it('dry-runs the removal of one document and emits its operation with the variant count', async () => {
            const document = createDocument('Manual');
            variantSync.mockResolvedValueOnce(['variant-a', 'variant-b']);
            const modal = await createWrapper({ documents: [document], hasVariants: true });

            expect(getRemoveFromVariants(modal).element.checked).toBe(true);

            await confirmRemoval(modal);

            const operation = { mediaId: document.mediaId, title: 'Manual' };
            expect(variantSync).toHaveBeenCalledTimes(1);
            expect(variantSync).toHaveBeenCalledWith(product.id, [operation], true);
            expect(removals(modal)).toEqual([{ operations: { [document.id]: operation }, variantCount: 2 }]);
            expect(getVariantOperations()).toEqual({});
        });

        it('matches a document by its queued operation and drops the queued changes', async () => {
            const document = createDocument('Manual');
            const queued = { mediaId: 'old-media', title: 'Old', changes: { title: 'Manual', mediaId: document.mediaId } };
            Shopware.Store.get('swProductDetail').documentVariantOperations = { [document.id]: queued };
            variantSync.mockResolvedValueOnce(['variant-a']);
            const modal = await createWrapper({ documents: [document], hasVariants: true });

            await confirmRemoval(modal);

            const operation = { mediaId: 'old-media', title: 'Old' };
            expect(variantSync).toHaveBeenCalledWith(product.id, [operation], true);
            expect(removals(modal)).toEqual([{ operations: { [document.id]: operation }, variantCount: 1 }]);
            expect(getVariantOperations()).toEqual({ [document.id]: queued });
        });

        it('emits one operation per document with a file and skips the dry run for several documents', async () => {
            const manual = createDocument('Manual');
            const untitled = createDocument('');
            const withoutFile = createDocument('Missing file', null);
            const modal = await createWrapper({ documents: [manual, untitled, withoutFile], hasVariants: true });

            await confirmRemoval(modal);

            const operations = {
                [manual.id]: { mediaId: manual.mediaId, title: 'Manual' },
                [untitled.id]: { mediaId: untitled.mediaId, title: null },
            };
            expect(variantSync).not.toHaveBeenCalled();
            expect(removals(modal)).toEqual([{ operations, variantCount: 0 }]);
        });

        it.each([
            { scope: 'the language version of one document', titles: ['Manual'] },
            { scope: 'the language versions of several documents', titles: ['Manual', 'Certificate'] },
        ])('clears $scope on the variants without a dry run', async ({ titles }) => {
            Shopware.Context.api.languageId = GERMAN_LANGUAGE_ID;
            const documents = titles.map((title) => createDocument(title));
            const modal = await createWrapper({ documents, languageName: 'Deutsch', hasVariants: true });

            expect(getRemoveFromVariants(modal).element.checked).toBe(true);

            await confirmRemoval(modal);

            const changes = { title: null, mediaId: null };
            const operations = Object.fromEntries(
                documents.map((document, index) => [
                    document.id,
                    { mediaId: document.mediaId, title: titles[index], changes },
                ]),
            );
            expect(variantSync).not.toHaveBeenCalled();
            expect(removals(modal)).toEqual([{ operations, variantCount: 0 }]);
        });

        it('leaves the variants untouched when removing from variants is unchecked', async () => {
            const modal = await createWrapper({ documents: [createDocument('Manual')], hasVariants: true });
            await getRemoveFromVariants(modal).setValue(false);

            await confirmRemoval(modal);

            expect(variantSync).not.toHaveBeenCalled();
            expect(removals(modal)).toEqual([{ operations: {}, variantCount: 0 }]);
            expect(getVariantOperations()).toEqual({});
        });

        it('blocks closing and repeated removals while the dry run runs', async () => {
            const document = createDocument('Manual');
            const finishDryRun = holdDryRun();
            const modal = await createWrapper({ documents: [document], hasVariants: true });
            await confirmRemoval(modal);

            expect(getButton(modal, 'global.default.cancel').attributes('disabled')).toBeDefined();
            expect(modal.get('.sw-product-document-form__confirm-remove').attributes('disabled')).toBeDefined();
            expect(getRemoveFromVariants(modal).element.disabled).toBe(true);

            await getButton(modal, 'global.default.cancel').trigger('click');
            modal.getComponent<ComponentPublicInstance>({ name: 'ModalRootStub' }).vm.$emit('change', false);
            await confirmRemoval(modal);

            expect(modal.emitted('close')).toBeUndefined();
            expect(modal.emitted('remove')).toBeUndefined();
            expect(variantSync).toHaveBeenCalledTimes(1);

            finishDryRun(['variant-a']);
            await flushPromises();

            const operations = { [document.id]: { mediaId: document.mediaId, title: 'Manual' } };
            expect(removals(modal)).toEqual([{ operations, variantCount: 1 }]);
        });

        it('shows an error and keeps the modal open when the dry run fails', async () => {
            variantSync.mockRejectedValueOnce(new Error('Server error'));
            const modal = await createWrapper({ documents: [createDocument('Manual')], hasVariants: true });
            await confirmRemoval(modal);

            expect(mockCreateNotificationError).toHaveBeenCalledWith({
                message: 'sw-product.documentForm.errorLoadVariants',
            });
            expect(modal.emitted('remove')).toBeUndefined();
            expect(getButton(modal, 'global.default.cancel').attributes('disabled')).toBeUndefined();
            expect(modal.get('.sw-product-document-form__confirm-remove').attributes('disabled')).toBeUndefined();

            variantSync.mockResolvedValueOnce(['variant-a']);
            await confirmRemoval(modal);

            expect(modal.emitted('remove')).toHaveLength(1);
        });

        it.each([
            { scenario: 'another product was loaded', isSameProduct: false },
            { scenario: 'the same product was reloaded', isSameProduct: true },
        ])('drops the result when $scenario during the dry run', async ({ isSameProduct }) => {
            const finishDryRun = holdDryRun();
            const modal = await createWrapper({ documents: [createDocument('Manual')], hasVariants: true });
            await confirmRemoval(modal);

            loadProduct(isSameProduct ? product.id : null);
            finishDryRun(['variant-a']);
            await flushPromises();

            expect(modal.emitted('remove')).toBeUndefined();
        });
    });
});
