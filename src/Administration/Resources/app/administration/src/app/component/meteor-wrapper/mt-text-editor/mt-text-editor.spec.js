/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { Node, mergeAttributes } from './tiptap';

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

    /**
     * A custom node shaped the way the inline mapping chip is: serialization carries only the marker attribute, while
     * the visible chip — class and human label — comes from a plain-DOM node view.
     *
     * Three things at once. `tipTapConfig` is not declared as a prop on this wrapper, so it only reaches the editor
     * through `$attrs`. The extension is built from the admin's hoisted copy of `@tiptap/core` while the editor runs
     * the copy bundled into the Meteor library, so this proves an extension survives crossing that boundary. And most
     * importantly it pins the round-trip: this editor parses `modelValue` on mount and locks itself behind a review
     * gate if re-serializing differs, so a node that renders anything cosmetic into its serialized form makes the
     * field unusable on every refresh.
     */
    const exampleChipNode = () =>
        Node.create({
            name: 'exampleChip',
            group: 'inline',
            inline: true,
            atom: true,
            addAttributes: () => ({
                path: {
                    default: null,
                    parseHTML: (element) => element.getAttribute('data-example-chip'),
                    renderHTML: (attributes) => ({ 'data-example-chip': attributes.path }),
                },
            }),
            parseHTML: () => [{ tag: 'span[data-example-chip]' }],
            renderHTML: ({ HTMLAttributes }) => [
                'span',
                mergeAttributes(HTMLAttributes),
            ],
            addNodeView: () => ({ node }) => {
                const dom = document.createElement('span');

                dom.className = 'example-chip';
                dom.setAttribute('data-example-chip', node.attrs.path);
                dom.textContent = `label for ${node.attrs.path}`;

                return { dom };
            },
        });

    it('should forward tipTapConfig through $attrs, so a custom node reaches the editor schema', async () => {
        const wrapper = await createWrapper({
            props: {
                modelValue: '<p>Buy the <span data-example-chip="product.name"></span> today</p>',
                tipTapConfig: {
                    extensions: [exampleChipNode()],
                },
            },
        });

        await flushPromises();

        const chip = wrapper.find('.example-chip');

        expect(chip.exists()).toBe(true);
        expect(chip.attributes('data-example-chip')).toBe('product.name');
        expect(chip.text()).toBe('label for product.name');
    });

    it('should not raise the review gate for a custom node whose serialized form round-trips', async () => {
        const wrapper = await createWrapper({
            props: {
                modelValue: '<p>Buy the <span data-example-chip="product.name"></span> today</p>',
                tipTapConfig: {
                    extensions: [exampleChipNode()],
                },
            },
        });

        await flushPromises();

        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(false);
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
