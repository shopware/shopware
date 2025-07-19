/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(
    additionalOptions = {
        slots: {},
        props: {},
    },
) {
    return mount(await wrapTestComponent('mt-text-editor', { sync: true }), {
        props: {
            ...additionalOptions.props,
        },
        slots: {
            ...additionalOptions.slots,
        },
        global: {
            stubs: {
                'sw-text-editor-toolbar-button-link': true,
            },
        },
    });
}

describe('src/app/component/meteor-wrapper/mt-text-editor', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should use custom link button', async () => {
        const wrapper = await createWrapper();

        const linkButton = wrapper.find('sw-text-editor-toolbar-button-link-stub');

        expect(linkButton.isVisible()).toBe(true);
    });

    it('should dynamically pass all slots to the underlying text editor', async () => {
        // Fill slot with example content
        const buttonLinkSlotContent = '<p class="example-slot-content">Example slot content</p>';

        const wrapper = await createWrapper({
            slots: {
                button_link: buttonLinkSlotContent,
            },
        });

        // Find the slot content in the rendered component
        const slotContent = wrapper.find('.example-slot-content');
        expect(slotContent.text()).toBe('Example slot content');
    });

    it('should dynamically pass all custom buttons to the underlying text editor', async () => {
        const wrapper = await createWrapper({
            props: {
                customButtons: [
                    {
                        name: 'custom-button',
                        label: 'custom.button.label',
                    },
                ],
            },
        });

        // Check if button with aria-label "custom.button.label" is present
        const button = wrapper.find('[aria-label="custom.button.label"]');
        expect(button.exists()).toBe(true);
    });

    it('should dynamically pass all excluded buttons to the underlying text editor', async () => {
        const wrapper = await createWrapper();

        // Check if button with aria-label "Bold" is present
        let button = wrapper.find('[aria-label="mt-text-editor-toolbar.buttons.bold"]');
        expect(button.exists()).toBe(true);

        // Exclude the bold button
        await wrapper.setProps({
            excludedButtons: [
                'bold',
            ],
        });

        // Check if button with aria-label "Bold" is not present
        button = wrapper.find('[aria-label="mt-text-editor-toolbar.buttons.bold"]');
        expect(button.exists()).toBe(false);
    });

    it('should bind the v-model to the underlying text editor', async () => {
        const wrapper = await createWrapper();

        // Set the v-model value
        await wrapper.setProps({
            modelValue: '<p>Example content binded via modelValue</p>',
        });

        // Check if the content is rendered in the text editor
        const editorContent = wrapper.find('.mt-text-editor__content');
        expect(editorContent.text()).toBe('Example content binded via modelValue');

        // Change the content in the text editor
        const editorComponent = wrapper.findComponent('.mt-text-editor');
        editorComponent.vm.$emit('update:modelValue', '<p>Example content changed via text editor</p>');

        // Check if the new content is set in the v-model
        expect(wrapper.emitted()['update:modelValue'][0]).toEqual(['<p>Example content changed via text editor</p>']);
    });
});
