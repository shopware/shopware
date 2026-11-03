/**
 * @sw-package inventory
 */
import { shallowMount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import { createMedia } from '../sw-product-document-form/sw-product-document-form.spec/helpers';

async function createWrapper(props = {}) {
    return shallowMount(await wrapTestComponent('sw-product-document-edit-modal', { sync: true }), {
        props: {
            documentId: 'document',
            documentTitle: 'Manual',
            uploadTag: 'replace-document',
            uploadHint: 'PDF, up to 20 MB',
            allowedExtensions: ['pdf'],
            maxFileSize: 20 * 1024 * 1024,
            canEdit: true,
            ...props,
        },
        global: {
            stubs: {
                'mt-modal-root': { template: '<div><slot /></div>' },
                'mt-modal': { template: '<div><slot /><slot name="footer" /></div>' },
                'mt-icon': true,
                'mt-text-field': {
                    props: ['modelValue', 'error', 'disabled'],
                    template: '<input :value="modelValue" :disabled="disabled" />',
                },
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-field-error': { props: ['error'], template: '<div />' },
            },
        },
    });
}

describe('sw-product-document-edit-modal', () => {
    it('emits edited names, Save and Cancel without mutating the supplied media', async () => {
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

    it('forwards uploaded files and media-library selection to the draft owner', async () => {
        const wrapper = await createWrapper();
        const upload = { targetId: createMedia().id };
        wrapper.getComponent<ComponentPublicInstance>('sw-upload-listener-stub').vm.$emit('media-upload-finish', upload);
        wrapper.getComponent<ComponentPublicInstance>('sw-media-upload-v2-stub').vm.$emit('media-upload-sidebar-open');

        expect(wrapper.emitted('upload')).toEqual([[upload]]);
        expect(wrapper.emitted('select-media')).toHaveLength(1);
    });

    it('shows the selected file and emits removal without deleting media', async () => {
        const media = createMedia('manual');
        const wrapper = await createWrapper({ editingMedia: media });
        expect(wrapper.get('.sw-product-document-form__file-name').text()).toBe('manual.pdf');

        await wrapper.get('.sw-product-document-form__remove-file').trigger('click');

        expect(wrapper.emitted('remove-file')).toHaveLength(1);
        expect(media.fileName).toBe('manual');
    });

    it.each([{ canEdit: false }, { isSavingDocument: true }])('disables editing when %o', async (props) => {
        const wrapper = await createWrapper({ ...props, editingMedia: createMedia() });
        expect(wrapper.get('.sw-product-document-form__name-input').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-product-document-form__remove-file').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-product-document-form__save-document').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-product-document-form__save-document').trigger('click');
        expect(wrapper.emitted('save')).toBeUndefined();
    });

    it('passes required-field errors to the corresponding inputs', async () => {
        const nameError = { code: 'PRODUCT_DOCUMENT_NAME_REQUIRED', detail: 'Enter a name.' };
        const fileError = { code: 'PRODUCT_DOCUMENT_FILE_REQUIRED', detail: 'Select a file.' };
        const wrapper = await createWrapper({ documentNameError: nameError, documentFileError: fileError });

        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-product-document-form__name-input').props()).toMatchObject(
            { error: nameError },
        );
        expect(wrapper.getComponent<ComponentPublicInstance>('.sw-product-document-form__file-error').props()).toMatchObject(
            { error: fileError },
        );
        expect(wrapper.get('.sw-product-document-form__file-error').attributes('role')).toBe('alert');
    });
});
