/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-mail-template-preview-modal', { sync: true }), {
        props: {
            isLoading: false,
            mailPreview: {
                subject: { type: 'success', content: 'Subject' },
                senderName: { type: 'success', content: 'Sender' },
                headerPlain: { type: 'success', content: 'Header plain' },
                contentPlain: { type: 'success', content: 'Content plain' },
                footerPlain: { type: 'success', content: 'Footer plain' },
                headerHtml: { type: 'success', content: '<div>Header</div>' },
                contentHtml: { type: 'success', content: '<div>Content</div>' },
                footerHtml: { type: 'success', content: '<div>Footer</div>' },
            },
        },
        global: {
            stubs: {
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot></slot>
                            <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'mt-banner': {
                    template: '<div><slot></slot></div>',
                },
                'mt-button': {
                    template: '<button @click="$emit(\'click\')"><slot></slot></button>',
                },
            },
        },
    });
}

describe('modules/sw-mail-template/component/sw-mail-template-preview-modal', () => {
    it('emits modal-close when the close button is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted()['modal-close']).toHaveLength(1);
    });
});
