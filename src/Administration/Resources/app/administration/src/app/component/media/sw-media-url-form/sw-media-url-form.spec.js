/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-media-url-form', { sync: true }), {
        props: {
            variant: 'inline',
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'mt-text-field': true,
                'mt-button': true,
                'mt-modal': {
                    template: `
                        <div class="mt-modal" role="dialog" aria-modal="true">
                            <div class="mt-modal__header">
                                <div class="mt-modal__header-content">
                                    <h2 class="mt-modal__title"></h2>
                                </div>
                            </div>
                            <div class="mt-modal__content">
                                <slot></slot>
                            </div>
                            <div class="mt-modal__footer">
                                <slot name="footer"></slot>
                            </div>
                        </div>
                    `,
                },
                'mt-modal-root': {
                    template: `
                        <div v-if="isOpen">
                            <slot></slot>
                        </div>
                    `,
                    props: {
                        isOpen: {
                            type: Boolean,
                            default: false,
                        },
                    },
                },
            },
        },
        ...customOptions,
    });
}

describe('src/app/component/media/sw-media-url-form', () => {
    it('should render in inline mode by default', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-media-url-form__url-input').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-url-form__submit-button').exists()).toBeTruthy();
    });

    it('should render in modal mode when variant is set to modal', async () => {
        const wrapper = await createWrapper({
            props: {
                variant: 'modal',
            },
        });
        await flushPromises();

        await wrapper.setData({ showModal: true });
        await flushPromises();
        await wrapper.vm.$nextTick();

        const modal = wrapper.find('.mt-modal');
        expect(modal.exists()).toBeTruthy();
        expect(modal.attributes('role')).toBe('dialog');
        expect(modal.attributes('aria-modal')).toBe('true');

        const modalHeader = modal.find('.mt-modal__header');
        expect(modalHeader.exists()).toBeTruthy();

        const modalContent = modal.find('.mt-modal__content');
        expect(modalContent.exists()).toBeTruthy();
        expect(modalContent.find('.sw-media-url-form__url-input').exists()).toBeTruthy();

        const modalFooter = modal.find('.mt-modal__footer');
        expect(modalFooter.exists()).toBeTruthy();
        expect(modalFooter.find('.sw-media-url-form__submit-button').exists()).toBeTruthy();
    });

    it('should render in inline mode when variant is not modal', async () => {
        const wrapper = await createWrapper({
            props: {
                variant: 'inline',
            },
        });
        await flushPromises();

        const modal = wrapper.find('.mt-modal');
        expect(modal.exists()).toBeFalsy();

        const urlInput = wrapper.find('.sw-media-url-form__url-input');
        const submitButton = wrapper.find('.sw-media-url-form__submit-button');

        expect(urlInput.exists()).toBeTruthy();
        expect(submitButton.exists()).toBeTruthy();

        const modalRoot = wrapper.find('mt-modal-root');
        expect(modalRoot.exists()).toBeFalsy();
    });

    it('should show file extension input when URL has no extension', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image',
        });
        await flushPromises();

        expect(wrapper.find('.sw-media-url-form__extension-input').exists()).toBeTruthy();
    });

    it('should not show file extension input when URL has extension', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image.jpg',
        });
        await flushPromises();

        expect(wrapper.find('.sw-media-url-form__extension-input').exists()).toBeFalsy();
    });

    it('should emit media-url-form-submit event with correct data when valid URL is submitted', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image.jpg',
        });
        await flushPromises();

        const submitButton = wrapper.find('.sw-media-url-form__submit-button');
        await submitButton.trigger('click');

        expect(wrapper.emitted('media-url-form-submit')).toBeTruthy();
        expect(wrapper.emitted('media-url-form-submit')[0][0]).toEqual({
            originalDomEvent: expect.any(Object),
            url: expect.any(URL),
            fileExtension: 'jpg',
        });
    });

    it('should not emit media-url-form-submit event when URL is invalid', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'invalid-url',
        });
        await flushPromises();

        const submitButton = wrapper.find('.sw-media-url-form__submit-button');
        await submitButton.trigger('click');

        expect(wrapper.emitted('media-url-form-submit')).toBeFalsy();
    });

    it('should show error message when URL is invalid', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'invalid-url',
        });
        await flushPromises();

        expect(wrapper.find('.sw-media-url-form__url-input').attributes('error')).toBeDefined();
    });

    it('should emit modal-close event when modal is closed', async () => {
        const wrapper = await createWrapper({
            props: {
                variant: 'modal',
            },
        });
        await flushPromises();

        await wrapper.vm.onModalChange(false);

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should disable submit button when URL is invalid', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'invalid-url',
        });
        await flushPromises();

        const submitButton = wrapper.find('.sw-media-url-form__submit-button');
        expect(submitButton.attributes('disabled')).toBe('true');
    });

    it('should enable submit button when URL is valid', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image.jpg',
        });
        await flushPromises();

        const submitButton = wrapper.find('.sw-media-url-form__submit-button');
        expect(submitButton.attributes('disabled')).toBe('false');
    });

    it('should handle file extension from URL correctly', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image.jpg',
        });
        await flushPromises();

        expect(wrapper.vm.extensionFromUrl).toBe('jpg');
    });

    it('should handle file extension from input when URL has no extension', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: 'https://example.com/image',
            extensionFromInput: 'png',
        });
        await flushPromises();

        expect(wrapper.vm.fileExtension).toBe('png');
    });

    it('should handle empty URL input', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            url: '',
        });
        await flushPromises();

        expect(wrapper.vm.hasInvalidInput).toBeFalsy();
        expect(wrapper.vm.isValid).toBeFalsy();
    });

    it('should handle modal visibility state changes', async () => {
        const wrapper = await createWrapper({
            props: {
                variant: 'modal',
            },
        });
        await flushPromises();

        expect(wrapper.vm.showModal).toBeTruthy();

        await wrapper.vm.onModalChange(false);
        expect(wrapper.vm.showModal).toBeFalsy();

        await wrapper.vm.onModalChange(true);
        expect(wrapper.vm.showModal).toBeTruthy();
    });
});
