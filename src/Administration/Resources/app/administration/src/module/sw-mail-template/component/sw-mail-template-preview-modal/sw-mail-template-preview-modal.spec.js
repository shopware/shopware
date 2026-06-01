/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper(props = {}) {
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
            ...props,
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
                    template: '<button><slot></slot></button>',
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

    it('hides preview content while loading', async () => {
        const wrapper = await createWrapper({ isLoading: true });

        expect(wrapper.find('.sw-mail-template-preview-modal__subject').exists()).toBe(false);
        expect(wrapper.findAll('.sw-mail-template-preview-modal__html-content')).toHaveLength(0);
    });

    it('renders html preview content', async () => {
        const wrapper = await createWrapper();

        const htmlContents = wrapper.findAll('.sw-mail-template-preview-modal__html-content');

        expect(htmlContents).toHaveLength(3);
        expect(htmlContents.at(0).html()).toContain('<div>Header</div>');
    });

    it('renders error banners instead of success content for error branches', async () => {
        const wrapper = await createWrapper({
            mailPreview: {
                subject: {
                    type: 'error',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'subject failed.',
                },
                senderName: { type: 'success', content: 'Sender' },
                headerPlain: { type: 'success', content: 'Header plain' },
                contentPlain: {
                    type: 'error',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'plain content failed.',
                },
                footerPlain: { type: 'success', content: 'Footer plain' },
                headerHtml: { type: 'success', content: '<div>Header</div>' },
                contentHtml: {
                    type: 'error',
                    errorTitle: 'Twig syntax error',
                    errorMessage: 'html content failed.',
                },
                footerHtml: { type: 'success', content: '<div>Footer</div>' },
            },
        });

        expect(wrapper.find('.sw-mail-template-preview-modal__subject-error').exists()).toBe(true);
        expect(wrapper.find('.sw-mail-template-preview-modal__subject-content').exists()).toBe(false);
        expect(wrapper.findAll('.sw-mail-template-preview-modal__plain-text-error')).toHaveLength(1);
        expect(wrapper.find('.sw-mail-template-preview-modal__plain-text').text()).toContain('plain content failed.');
        expect(wrapper.findAll('.sw-mail-template-preview-modal__html-error')).toHaveLength(1);
        expect(wrapper.find('.sw-mail-template-preview-modal__html').text()).toContain('html content failed.');
        expect(wrapper.findAll('.sw-mail-template-preview-modal__html-content')).toHaveLength(2);
    });
});
