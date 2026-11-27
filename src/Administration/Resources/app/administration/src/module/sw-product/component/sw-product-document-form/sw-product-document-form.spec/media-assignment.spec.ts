/**
 * @sw-package inventory
 */
import repositoryFactory from 'test/_setup/_mocks_/repositoryFactory.service.mock';
import type { ComponentPublicInstance } from 'vue';
import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import fileValidationService from 'src/app/service/file-validation.service';

import {
    createProduct,
    cleanupWrappers,
    createWrapper,
    createMedia,
    addDocuments,
    clickButton,
    getDocuments,
    mockSnackbar,
} from './helpers';

describe('sw-product-document-form media assignment', () => {
    const originalLanguage = Shopware.Context.api.languageId;

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        Object.assign(store, { product: createProduct() });
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        Shopware.Context.api.languageId = originalLanguage;
        cleanupWrappers();
    });

    it('adds an assignment to the product save payload without copying the media file', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addDocuments(wrapper, [media]);

        expect(wrapper.findAll('.document-row')).toHaveLength(1);
        expect(wrapper.text()).toContain('manual');
        const payload = new ChangesetGenerator().generate(Shopware.Store.get('swProductDetail').product);
        expect(payload).toMatchObject({
            changes: { documents: [expect.objectContaining({ mediaId: media.id, title: 'manual', position: 0 })] },
        });
        expect(JSON.stringify(payload)).not.toContain('"media":');
    });

    it('keeps unsaved file details after returning to the document form', async () => {
        const wrapper = await createWrapper();
        await addDocuments(wrapper, [createMedia('unsaved-manual')]);
        wrapper.unmount();

        const reopened = await createWrapper();
        expect(reopened.text()).toContain('unsaved-manual.pdf');
        expect(reopened.text()).toContain('1.00KB');
    });

    it.each([
        ['manual.pdf', 'application/pdf'],
        ['manual.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ['manual.jpg', 'image/jpeg'],
        ['manual.jpeg', 'image/jpeg'],
        ['manual.png', 'image/png'],
    ])('accepts %s through the media modal upload validation', async (name, type) => {
        const wrapper = await createWrapper();
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');
        await flushPromises();

        const accept = wrapper.getComponent('sw-media-modal-v2-stub').attributes('file-accept')!;
        const { checkByType } = fileValidationService();
        expect(checkByType(new File(['document'], name, { type }), accept)).toBe(true);
        expect(checkByType(new File(['video'], 'video.mp4', { type: 'video/mp4' }), accept)).toBe(false);
    });

    it('rejects duplicate media', async () => {
        const wrapper = await createWrapper();
        const media = createMedia();
        await addDocuments(wrapper, [media]);
        mockSnackbar.mockClear();

        await addDocuments(wrapper, [media]);

        expect(wrapper.findAll('.document-row')).toHaveLength(1);
        expect(mockSnackbar).toHaveBeenCalledWith({
            variant: 'error',
            message: 'sw-product.documentForm.errorDocumentDuplicated',
        });
    });

    it('renders the media association loaded through translations', async () => {
        const media = createMedia('reloaded');
        const document = repositoryFactory.create('product_document').create();
        Object.assign(document, {
            title: null,
            translated: { title: 'Translated manual', mediaId: media.id },
            mediaId: media.id,
            translations: [{ mediaId: media.id, media }],
        });
        getDocuments()?.add(document);
        const wrapper = await createWrapper();
        expect(wrapper.text()).toContain('Translated manual');
        expect(wrapper.text()).toContain('reloaded.pdf');
        expect(wrapper.text()).toContain('1.00KB');
    });

    it('disables document management for a product viewer', async () => {
        Object.assign(global, { activeAclRoles: ['product.viewer'] });
        const wrapper = await createWrapper();
        expect(wrapper.get('.sw-product-document-form__empty-state button').attributes('disabled')).toBeDefined();
    });

    it('requires new documents to be created in the default language', async () => {
        Shopware.Context.api.languageId = repositoryFactory.create('language').create().id;
        const wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.find('.sw-product-document-form__empty-state .mt-button').exists()).toBe(false);
        expect(wrapper.find('.sw-product-document-form__language-hint').exists()).toBe(false);
        const switchLanguage = jest.fn();
        Shopware.Utils.EventBus.on('on-change-language-clicked', switchLanguage);
        try {
            await wrapper.get('.sw-product-document-form__empty-state .mt-link').trigger('click');
            expect(switchLanguage).toHaveBeenCalledWith(Shopware.Context.api.systemLanguageId);
        } finally {
            Shopware.Utils.EventBus.off('on-change-language-clicked', switchLanguage);
        }
    });

    it('adds all selected documents when more than ten files are selected', async () => {
        const wrapper = await createWrapper();
        await addDocuments(
            wrapper,
            Array.from({ length: 9 }, (_, index) => createMedia(`manual-${index}`)),
        );
        const media = [createMedia('tenth'), createMedia('eleventh'), createMedia('twelfth')];
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        await addDocuments(wrapper, media);

        expect(getDocuments()).toHaveLength(12);
        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        expect(
            Array.from(getDocuments()!)
                .slice(9)
                .map((document) => document.mediaId),
        ).toEqual(media.map((file) => file.id));
        expect(wrapper.get('.sw-product-document-form__add-button button').attributes('disabled')).toBeUndefined();
        expect(notificationSpy).not.toHaveBeenCalled();
    });

    it.each([9, 10])('adds an uploaded file to %s documents and keeps Add documents enabled', async (count) => {
        const media = createMedia('uploaded manual');
        const repository = repositoryFactory.create('media');
        jest.spyOn(repository, 'get').mockResolvedValue(media);
        const wrapper = await createWrapper(repository);
        await addDocuments(
            wrapper,
            Array.from({ length: count }, (_, index) => createMedia(`manual-${index}`)),
        );
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');

        wrapper
            .getComponent<ComponentPublicInstance>('sw-upload-listener-stub')
            .vm.$emit('media-upload-finish', { targetId: media.id });
        await flushPromises();

        expect(getDocuments()).toHaveLength(count + 1);
        expect(wrapper.findAll('.document-row')).toHaveLength(10);
        expect(getDocuments()?.[count].mediaId).toBe(media.id);
        expect(wrapper.find('.sw-media-upload-v2__button.upload').exists()).toBe(false);
        expect(wrapper.get('.sw-product-document-form__add-button button').attributes('disabled')).toBeUndefined();
    });

    it.each(['unsupported extension', 'oversized file', 'unknown size'])(
        'rejects selected media when validation fails: %s',
        async (failure) => {
            const wrapper = await createWrapper();
            const media = createMedia();
            if (failure === 'unsupported extension') {
                media.fileExtension = 'exe';
            } else if (failure === 'oversized file') {
                media.fileSize = 20 * 1024 * 1024 + 1;
            } else {
                media.fileSize = undefined;
            }
            await addDocuments(wrapper, [media]);
            expect(wrapper.findAll('.document-row')).toHaveLength(0);
        },
    );
});
