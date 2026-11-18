/**
 * @sw-package inventory
 */
import type { ComponentPublicInstance } from 'vue';
import {
    addDocuments,
    cleanupWrappers,
    clickButton,
    createMedia,
    createProduct,
    createWrapper,
    getDocuments,
} from './helpers';

describe('sw-product-document-form backend configuration', () => {
    const originalLanguageId = Shopware.Context.api.languageId;
    const documentConfig = {
        allowedExtensions: ['txt', 'md'],
        allowedMimeTypesByExtension: {
            txt: ['text/plain', 'application/x-custom-document'],
            md: ['text/plain', 'text/markdown'],
        },
        maxFileSize: 2 * 1024 * 1024,
    };

    beforeEach(() => {
        Object.assign(global, { activeAclRoles: ['product.editor'] });
        Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = createProduct();
        store.parentProduct = createProduct();
    });

    afterEach(() => {
        cleanupWrappers();
        Shopware.Context.api.languageId = originalLanguageId;
    });

    it('uses the API extensions, MIME types and size limit in the upload pickers', async () => {
        const wrapper = await createWrapper(undefined, undefined, undefined, { documentConfig });
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        const upload = wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2');

        expect(upload.props()).toMatchObject({
            extensionAccept: 'txt,md',
            extensionMimeTypesByExtension: documentConfig.allowedMimeTypesByExtension,
            maxFileSize: documentConfig.maxFileSize,
        });

        upload.vm.$emit('media-upload-sidebar-open');
        await flushPromises();

        expect(wrapper.get('sw-media-modal-v2-stub').attributes('file-accept')).toBe(
            'text/plain,application/x-custom-document,text/markdown',
        );
    });

    it.each([
        { extension: 'txt', size: documentConfig.maxFileSize, accepted: true },
        { extension: 'txt', size: documentConfig.maxFileSize + 1, accepted: false },
        { extension: 'pdf', size: 1024, accepted: false },
    ])('validates $extension files of $size bytes against the API rules', async ({ extension, size, accepted }) => {
        const wrapper = await createWrapper(undefined, undefined, undefined, { documentConfig });
        const media = createMedia('configured-document');
        media.fileExtension = extension;
        media.fileSize = size;

        await addDocuments(wrapper, [media]);

        expect(getDocuments()).toHaveLength(accepted ? 1 : 0);
        expect(wrapper.findAll('.document-row')).toHaveLength(accepted ? 1 : 0);
    });

    it('uses the API rules when replacing an existing file', async () => {
        const wrapper = await createWrapper(undefined, undefined, undefined, { documentConfig });
        const media = createMedia('original');
        media.fileExtension = 'txt';
        await addDocuments(wrapper, [media]);
        await wrapper.findAll('sw-context-menu-item-stub')[0].trigger('click');

        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').props()).toMatchObject({
            extensionAccept: 'txt,md',
            extensionMimeTypesByExtension: documentConfig.allowedMimeTypesByExtension,
            maxFileSize: documentConfig.maxFileSize,
        });
    });

    it('updates the pickers and validation when the context configuration changes', async () => {
        const wrapper = await createWrapper(undefined, undefined, undefined, { documentConfig });
        await clickButton(wrapper, 'sw-product.documentForm.buttonAdd');
        const upload = wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2');
        expect(upload.props()).toMatchObject({ extensionAccept: 'txt,md' });

        Shopware.Store.get('context').app.config.settings!.productDocuments = {
            allowedExtensions: ['pdf'],
            allowedMimeTypesByExtension: { pdf: ['application/x-custom-pdf'] },
            maxFileSize: 512,
        };
        await flushPromises();

        expect(upload.props()).toMatchObject({ extensionAccept: 'pdf', maxFileSize: 512 });
        upload.vm.$emit('media-upload-sidebar-open');
        await flushPromises();
        const modal = wrapper.getComponent<ComponentPublicInstance>('sw-media-modal-v2-stub');
        expect(modal.attributes('file-accept')).toBe('application/x-custom-pdf');

        const previousExtension = createMedia('outdated-type');
        previousExtension.fileExtension = 'txt';
        previousExtension.fileSize = 512;
        const oversized = createMedia('too-large');
        oversized.fileSize = 513;
        const accepted = createMedia('accepted');
        accepted.fileSize = 512;
        modal.vm.$emit('media-modal-selection-change', [previousExtension, oversized, accepted]);
        await flushPromises();

        expect(getDocuments()).toHaveLength(1);
        expect(getDocuments()?.[0].mediaId).toBe(accepted.id);
        expect(wrapper.findAll('.document-row')).toHaveLength(1);
        expect(wrapper.get('.document-row').text()).toContain('accepted.pdf');
    });
});
