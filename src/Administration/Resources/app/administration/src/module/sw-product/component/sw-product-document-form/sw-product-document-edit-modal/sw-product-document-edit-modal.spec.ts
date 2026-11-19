/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import { createMedia } from '../sw-product-document-form.spec/helpers';

let mountedWrapper: VueWrapper;

async function createWrapper(props = {}) {
    mountedWrapper = shallowMount(await wrapTestComponent('sw-product-document-edit-modal', { sync: true }), {
        props: {
            documentTitle: 'Manual',
            uploadTag: 'replace-document',
            uploadHint: 'PDF, up to 20 MB',
            allowedExtensions: ['pdf'],
            maxFileSize: 20 * 1024 * 1024,
            canEdit: true,
            ...props,
        },
        global: {
            directives: { droppable: {} },
            provide: {
                mediaService: {
                    addListener: jest.fn(),
                    removeListener: jest.fn(),
                    removeByTag: jest.fn(),
                    getDefaultFolderId: jest.fn().mockResolvedValue(null),
                },
                mediaPresignedUploadService: {},
                fileValidationService: {},
            },
            stubs: {
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-icon': true,
                'mt-base-field': false,
                'mt-field-error': false,
                'sw-upload-listener': true,
                'sw-media-upload-v2': await wrapTestComponent('sw-media-upload-v2', { sync: true }),
                'sw-media-preview-v2': { props: ['source'], template: '<div class="document-preview" />' },
                'sw-field-error': { props: ['error'], template: '<div />' },
            },
        },
    });
    return mountedWrapper;
}

describe('sw-product-document-edit-modal', () => {
    afterEach(() => mountedWrapper?.unmount());
    it('emits name updates and forwards Apply and Cancel without changing the supplied media', async () => {
        const media = createMedia();
        const wrapper = await createWrapper({ editingMedia: media });
        const originalFileName = media.fileName;

        wrapper
            .getComponent<ComponentPublicInstance>('.sw-product-document-form__name-input')
            .vm.$emit('update:modelValue', 'Handbuch');
        await wrapper.get('.sw-product-document-form__save-document').trigger('click');
        const cancel = wrapper.findAll('button').find((button) => button.text() === 'global.default.cancel');
        await cancel!.trigger('click');

        expect(wrapper.emitted('update:documentTitle')).toEqual([['Handbuch']]);
        expect(wrapper.emitted('save')).toHaveLength(1);
        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(media.fileName).toBe(originalFileName);
    });

    it('limits the native name input to 255 characters and accepts a name at that limit', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input');
        const name = 'a'.repeat(255);

        expect(input.element.maxLength).toBe(255);
        await input.setValue(name);

        expect(wrapper.emitted('update:documentTitle')).toEqual([[name]]);
    });

    it('forwards upload completion and requests to open the media library', async () => {
        const wrapper = await createWrapper();
        const upload = { targetId: createMedia().id };
        wrapper.getComponent<ComponentPublicInstance>('sw-upload-listener-stub').vm.$emit('media-upload-finish', upload);
        wrapper.getComponent<ComponentPublicInstance>('.sw-media-upload-v2').vm.$emit('media-upload-sidebar-open');

        expect(wrapper.emitted('upload')).toEqual([[upload]]);
        expect(wrapper.emitted('media-select')).toHaveLength(1);
    });

    it('shows the selected file and emits a request to remove it', async () => {
        const media = createMedia('manual');
        const wrapper = await createWrapper({ editingMedia: media });
        expect(wrapper.get('.sw-media-upload-v2__file-headline').text()).toBe('manual.pdf');
        expect(wrapper.getComponent<ComponentPublicInstance>('.document-preview').props()).toMatchObject({
            source: { fileName: 'manual' },
        });

        await wrapper.get('.sw-media-upload-v2__remove-icon').trigger('click');

        expect(wrapper.emitted('file-remove')).toHaveLength(1);
        expect(media.fileName).toBe('manual');
    });

    it.each([{ canEdit: false }, { isApplyingDocumentEdit: true }])('disables editing when %o', async (props) => {
        const wrapper = await createWrapper({ ...props, editingMedia: createMedia() });
        expect(wrapper.get<HTMLInputElement>('.sw-product-document-form__name-input input').element.disabled).toBe(true);
        expect(wrapper.find('.sw-media-upload-v2__remove-icon').exists()).toBe(false);
        expect(wrapper.get('.sw-product-document-form__save-document').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-product-document-form__save-document').trigger('click');
        expect(wrapper.emitted('save')).toBeUndefined();
    });

    it('passes required-field errors to the corresponding inputs', async () => {
        const nameError = { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: 'Enter a name.' };
        const fileError = { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: 'Select a file.' };
        const wrapper = await createWrapper({ documentTitleError: nameError, documentFileError: fileError });

        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-product-document-form__name-input').props()).toMatchObject(
            { error: nameError },
        );
        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-product-document-form__file-error').props()).toMatchObject(
            { error: fileError },
        );
        expect(wrapper.get('.sw-product-document-form__file-error').attributes('role')).toBe('alert');
    });
});
