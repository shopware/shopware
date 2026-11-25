import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import inlineTextFieldComponent from './index';

type TextEditor = {
    commands: {
        setTextSelection: (position: number) => boolean;
        insertContent: (text: string) => boolean;
        deleteRange: (range: { from: number; to: number }) => boolean;
    };
    state: { selection: { from: number; to: number } };
    getHTML: () => string;
};

describe('Experience Studio inline mapping labels', () => {
    let wrapper: ReturnType<typeof mount> | null = null;
    const value = '<p>{{map:product.name}} text</p>';
    const candidate: ContentSystemMappingCandidate = {
        path: 'product.name',
        source: { type: 'root', id: 'product', path: 'name' },
        label: 'mapping.productName',
        description: '',
        group: 'basic',
        valueType: 'string',
        compatibleTypes: ['string'],
        inlineMappable: true,
        canonicalToken: '{{map:product.name}}',
        contextType: 'single',
    };

    beforeEach(() => {
        jest.useFakeTimers();
        const parseInlineMappingTokens = jest.fn((_text: string, revision: number) =>
            Promise.resolve({
                revision,
                tokens: [
                    {
                        path: 'product.name',
                        match: '{{map:product.name}}',
                        canonicalToken: '{{map:product.name}}',
                        offset: 3,
                        insideMarkup: false,
                        malformed: false,
                    },
                ],
            }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        jest.clearAllTimers();
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it.each([
        false,
        true,
    ])('updates labels without changing the document or caret when already typing=%s', async (typing) => {
        wrapper = mount(inlineTextFieldComponent as Component, {
            props: { value },
            global: {
                stubs: { 'sw-text-editor-toolbar-button-link': true },
                mocks: { $te: () => true, $t: (key: string) => (key === candidate.label ? 'Product name' : key) },
            },
        });
        await flushPromises();
        const getEditor = () => wrapper!.findComponent({ name: 'mt-text-editor-toolbar' }).props('editor') as TextEditor;
        const editor = getEditor();
        const chip = wrapper.find('.sw-experience-studio-mapping-token');
        expect(chip.text()).toBe('product.name');
        editor.commands.setTextSelection(3);
        if (typing) {
            editor.commands.insertContent('typed');
            await flushPromises();
        }
        const html = editor.getHTML();
        const selection = { from: editor.state.selection.from, to: editor.state.selection.to };
        const updates = wrapper.emitted('update:value')?.length ?? 0;

        await wrapper.setProps({ candidates: [candidate] });
        await flushPromises();

        expect(chip.text()).toBe('Product name');
        expect(wrapper.find('.sw-experience-studio-mapping-token').element).toBe(chip.element);
        expect(getEditor()).toBe(editor);
        expect(editor.getHTML()).toBe(html);
        expect(editor.state.selection.from).toBe(selection.from);
        expect(editor.state.selection.to).toBe(selection.to);
        expect(wrapper.emitted('update:value')?.length ?? 0).toBe(updates);
        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(false);
        const [insertButton] = wrapper.findComponent({ name: 'mt-text-editor' }).props('customButtons') as {
            disabled: (editor: TextEditor, globalDisabled: boolean) => boolean;
        }[];
        expect(insertButton.disabled(editor, false)).toBe(false);

        await wrapper.setProps({ candidates: [] });
        await flushPromises();

        expect(chip.text()).toBe('product.name');
        expect(editor.getHTML()).toBe(html);
        expect(wrapper.emitted('update:value')?.length ?? 0).toBe(updates);
        expect(insertButton.disabled(editor, false)).toBe(true);

        editor.commands.insertContent(' more');
        await flushPromises();
        const values = wrapper.emitted('update:value')!;
        expect(values[values.length - 1][0]).toContain('{{map:product.name}}');
        expect(values[values.length - 1][0]).toContain(' more');

        editor.commands.deleteRange({ from: 1, to: 2 });
        await flushPromises();
        expect(wrapper.find('.sw-experience-studio-mapping-token').exists()).toBe(false);
        await wrapper.setProps({ candidates: [candidate] });
        await flushPromises();

        expect(chip.element.textContent).toBe('product.name');
    });
});
