/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

type ProductDocument = Entity<'product_document'>;

type DocumentFormWrapper = VueWrapper<{
    documents: EntityCollection<'product_document'> | null;
    sortedDocuments: ProductDocument[];
    onUploadFinish: (event: { targetId: string }) => Promise<void>;
    onTitleInput: (document: unknown, value: string) => void;
    onTitleBlur: (document: unknown) => void;
    onMove: (document: ProductDocument, offset: number) => void;
    onRemove: (document: ProductDocument) => void;
    openMediaModal: (document: unknown) => void;
    onMediaSelection: (selection: unknown[]) => void;
    createNotificationError: (notification: { message: string }) => void;
}>;

type Version = {
    languageId: EntityKey<'language'>;
    title: string | null;
    mediaId: string | null;
    media?: ReturnType<typeof media>;
};

const EN = 'en-language-id' as EntityKey<'language'>;
const DE = 'de-language-id' as EntityKey<'language'>;

function media(id: string) {
    return {
        id,
        fileName: id,
        fileExtension: 'pdf',
        fileSize: 2048,
        uploadedAt: '2026-10-27T08:00:00.000Z',
        url: `https://shop.test/${id}.pdf`,
    };
}

function collection<EntityName extends keyof EntitySchema.Entities>(entity: EntityName, items: unknown[]) {
    return new EntityCollection(`/${entity}`, entity, Shopware.Context.api, null, items as Entity<EntityName>[]);
}

function document(
    id: string,
    position: number,
    own: { title: string | null; mediaId: string | null },
    versions: Version[],
    isNew = false,
) {
    return {
        id,
        position,
        productId: 'product-id',
        title: own.title,
        mediaId: own.mediaId,
        translated: { title: versions[0]?.title ?? null, mediaId: versions[0]?.mediaId ?? null },
        translations: collection('product_document_translation', versions),
        isNew: () => isNew,
    };
}

function setProduct(items: unknown[]) {
    Object.assign(Shopware.Store.get('swProductDetail'), {
        product: { id: 'product-id', documents: collection('product_document', items) },
    });
}

const manual = () =>
    document('manual', 0, { title: 'Manual', mediaId: 'manual-en' }, [
        { languageId: EN, title: 'Manual', mediaId: 'manual-en', media: media('manual-en') },
        { languageId: DE, title: 'Handbuch', mediaId: 'handbuch-de', media: media('handbuch-de') },
    ]);
const datasheet = () =>
    document('datasheet', 1, { title: 'Datasheet', mediaId: 'datasheet-en' }, [
        { languageId: EN, title: 'Datasheet', mediaId: 'datasheet-en', media: media('datasheet-en') },
    ]);

async function createWrapper({ props = {}, privileges = ['product.editor'] } = {}) {
    return mount(await wrapTestComponent('sw-product-document-form', { sync: true }), {
        props,
        global: {
            provide: {
                acl: { can: (privilege: string) => privileges.includes(privilege) },
                repositoryFactory: {
                    create: () => ({
                        create: () => ({
                            id: 'new-document',
                            translations: collection('product_document_translation', []),
                            isNew: () => true,
                        }),
                        get: (id: string) => Promise.resolve(media(id)),
                        search: () => Promise.resolve({ first: () => ({ folder: { id: 'folder-id' } }) }),
                    }),
                },
            },
            stubs: {
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-media-modal-v2': true,
                'mt-banner': { template: '<div class="mt-banner"><slot /></div>' },
                'sw-data-grid': {
                    props: [
                        'dataSource',
                        'isRecordDisabled',
                    ],
                    template: `<div class="grid">
                        <div v-for="item in dataSource" :key="item.id" class="row">
                            <slot name="column-title" v-bind="{ item }" />
                            <slot name="column-file" v-bind="{ item }" />
                            <slot name="actions" v-bind="{ item }" />
                        </div>
                    </div>`,
                },
                'mt-text-field': {
                    props: [
                        'modelValue',
                        'placeholder',
                        'disabled',
                        'size',
                    ],
                    template: '<input class="title" :value="modelValue" :placeholder="placeholder" :disabled="disabled" />',
                },
                'sw-label': { template: '<span class="fallback"><slot /></span>' },
                'sw-context-menu-item': {
                    props: ['disabled'],
                    emits: ['click'],
                    template: '<button class="menu-item" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                },
            },
        },
    }) as unknown as DocumentFormWrapper;
}

describe('module/sw-product/component/sw-product-document-form', () => {
    beforeEach(() => {
        Shopware.Store.get('swProductDetail').$reset();
        Shopware.Context.api.systemLanguageId = EN;
        Shopware.Context.api.languageId = EN;
        setProduct([datasheet(), manual()]);
    });

    it('lists the documents in their order with title and file', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const titles = wrapper.findAll('.title').map((input) => (input.element as HTMLInputElement).value);
        expect(titles).toEqual(['Manual', 'Datasheet']);
        expect(wrapper.text()).toContain('manual-en.pdf');
        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-product-document-form__variant-only').exists()).toBe(false);
    });

    it('shows the default language as fallback in another language and disables adding', async () => {
        Shopware.Context.api.languageId = DE;
        setProduct([
            document('datasheet', 0, { title: null, mediaId: null }, [
                { languageId: EN, title: 'Datasheet', mediaId: 'datasheet-en', media: media('datasheet-en') },
            ]),
        ]);

        const wrapper = await createWrapper();
        await flushPromises();

        const title = wrapper.find('.title').element as HTMLInputElement;
        expect(title.value).toBe('');
        expect(title.placeholder).toBe('Datasheet');
        expect(wrapper.find('.fallback').exists()).toBe(true);
        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-product-document-form__hint').exists()).toBe(true);
    });

    it('adds an uploaded file after the last document, titled with the file name', async () => {
        setProduct([
            document('manual', 1, { title: 'Manual', mediaId: 'manual-en' }, []),
            document('datasheet', 4, { title: 'Datasheet', mediaId: 'datasheet-en' }, []),
        ]);
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onUploadFinish({ targetId: 'certificate' });

        const added = wrapper.vm.documents?.get('new-document');
        expect(added?.title).toBe('certificate');
        expect(added?.mediaId).toBe('certificate');
        expect(added?.position).toBe(5);
    });

    it('rejects a file that is already a document of the product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError').mockImplementation(() => {});

        await wrapper.vm.onUploadFinish({ targetId: 'manual-en' });

        expect(notification).toHaveBeenCalled();
        expect(wrapper.vm.documents?.length).toBe(2);
    });

    it('replaces the file of a document unless another document has it', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError').mockImplementation(() => {});
        const [first] = wrapper.vm.sortedDocuments;

        wrapper.vm.openMediaModal(first);
        wrapper.vm.onMediaSelection([media('datasheet-en')]);

        expect(notification).toHaveBeenCalled();
        expect(first.mediaId).toBe('manual-en');

        wrapper.vm.onMediaSelection([media('manual-v2')]);

        expect(first.mediaId).toBe('manual-v2');
    });

    it('moves and removes documents and keeps the positions in order', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const [first] = wrapper.vm.sortedDocuments;
        wrapper.vm.onMove(first, 1);
        expect(wrapper.vm.sortedDocuments.map((item) => item.id)).toEqual(['datasheet', 'manual']);

        wrapper.vm.onRemove(wrapper.vm.sortedDocuments[0]);
        expect(wrapper.vm.sortedDocuments.map((item) => [item.id, item.position])).toEqual([['manual', 0]]);
    });

    it('gives a document its file name back when the title is emptied in the default language', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const [first] = wrapper.vm.sortedDocuments;
        wrapper.vm.onTitleInput(first, '');
        wrapper.vm.onTitleBlur(first);

        expect(first.title).toBe('manual-en');
    });

    it('edits a copy in the version it took over for the content language, else in its fields', async () => {
        Shopware.Context.api.languageId = DE;
        const translated = document(
            'translated',
            0,
            { title: null, mediaId: null },
            [
                { languageId: EN, title: 'Manual', mediaId: 'manual-en' },
                { languageId: DE, title: 'Handbuch', mediaId: 'handbuch-de' },
            ],
            true,
        );
        const untranslated = document(
            'untranslated',
            1,
            { title: null, mediaId: null },
            [{ languageId: EN, title: 'Datasheet', mediaId: 'datasheet-en' }],
            true,
        );
        setProduct([translated, untranslated]);

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onTitleInput(translated, 'Handbuch (blau)');
        wrapper.vm.onTitleInput(untranslated, 'Datenblatt');

        expect(translated.translations.find((translation) => translation.languageId === DE)?.title).toBe('Handbuch (blau)');
        expect(translated.title).toBeNull();
        expect(untranslated.title).toBe('Datenblatt');
        expect(untranslated.translations).toHaveLength(1);
    });

    it('is read-only when the documents are inherited', async () => {
        Object.assign(Shopware.Store.get('swProductDetail'), {
            parentProduct: { id: 'parent-id', documents: collection('product_document', [manual()]) },
        });

        const wrapper = await createWrapper({ props: { isInherited: true, disabled: true } });
        await flushPromises();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBe(false);
        expect((wrapper.find('.title').element as HTMLInputElement).disabled).toBe(true);
        expect(wrapper.find('.sw-product-document-form__variant-only').exists()).toBe(false);
    });

    it('labels the documents only an unlinked variant has, not its copies with a new title or file', async () => {
        Object.assign(Shopware.Store.get('swProductDetail'), {
            product: {
                id: 'variant-id',
                parentId: 'parent-id',
                documents: collection('product_document', [
                    document('renamed-copy', 0, { title: 'Manual (blue)', mediaId: 'manual-en' }, []),
                    document('copy-with-new-file', 1, { title: 'Datasheet', mediaId: 'datasheet-v2' }, []),
                    document('own', 2, { title: 'Colour chart', mediaId: 'colour-chart' }, []),
                ]),
            },
            parentProduct: { id: 'parent-id', documents: collection('product_document', [manual(), datasheet()]) },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const labelled = wrapper.findAll('.row').map((row) => row.find('.sw-product-document-form__variant-only').exists());
        expect(labelled).toEqual([
            false,
            false,
            true,
        ]);
        expect(wrapper.find('.sw-product-document-form__removed').exists()).toBe(false);
    });

    it('lists the parent documents an unlinked variant removed after its own ones, to add back', async () => {
        Object.assign(Shopware.Store.get('swProductDetail'), {
            product: {
                id: 'variant-id',
                parentId: 'parent-id',
                documents: collection('product_document', [
                    document('manual-copy', 0, { title: 'Manual', mediaId: 'manual-en' }, []),
                ]),
            },
            parentProduct: { id: 'parent-id', documents: collection('product_document', [manual(), datasheet()]) },
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const [own, removed] = wrapper.findAll('.row');
        expect(own.find('.sw-product-document-form__removed').exists()).toBe(false);
        expect(removed.find('.sw-product-document-form__removed-title').text()).toBe('Datasheet');
        expect(removed.find('.sw-product-document-form__removed').exists()).toBe(true);
        expect(removed.find('.title').exists()).toBe(false);
        expect(wrapper.vm.sortedDocuments.map((item) => item.id)).toEqual(['manual-copy']);
        expect(own.find('.sw-product-document-form__add-back').exists()).toBe(false);

        await removed.find('.sw-product-document-form__add-back').trigger('click');

        expect(wrapper.emitted('add-back')?.[0]).toEqual([expect.objectContaining({ id: 'datasheet' })]);
    });
});
