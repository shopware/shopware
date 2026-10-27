import { mount } from '@vue/test-utils';
import type { Component, ComponentPublicInstance } from 'vue';
import type { ContentSystemInlineMappingToken } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import inlineTextFieldComponent from './index';

type ParseResponse = { revision: number; tokens: ContentSystemInlineMappingToken[] };
type PendingParse = {
    value: string;
    revision: number;
    resolve: (response: ParseResponse) => void;
    reject: (error: Error) => void;
};
type TextEditor = {
    commands: {
        setTextSelection: (position: number) => boolean;
        insertContent: (text: string) => boolean;
    };
    state: { selection: { from: number; to: number } };
    getHTML: () => string;
};

describe('Experience Studio inline text parse feedback', () => {
    let wrapper: ReturnType<typeof mount> | null = null;
    let requests: PendingParse[];

    const value = '<p>{{map:category.description}} text</p>';
    const tokens: ContentSystemInlineMappingToken[] = [
        {
            path: 'category.description',
            match: '{{map:category.description}}',
            canonicalToken: '{{map:category.description}}',
            offset: 3,
            insideMarkup: false,
            malformed: false,
        },
    ];

    beforeEach(() => {
        jest.useFakeTimers();
        requests = [];
        const parseInlineMappingTokens = jest.fn(
            (text: string, revision: number) =>
                new Promise<ParseResponse>((resolve, reject) => {
                    requests.push({ value: text, revision, resolve, reject });
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

    const mountField = () => {
        wrapper = mount(inlineTextFieldComponent as Component, {
            props: { value },
            global: {
                stubs: { 'sw-text-editor-toolbar-button-link': true },
                mocks: { $t: (key: string) => `translated:${key}` },
            },
        });

        return wrapper;
    };

    const resolveParse = async (index: number, parsedTokens: ContentSystemInlineMappingToken[] = []) => {
        const request = requests[index];
        request.resolve({ revision: request.revision, tokens: parsedTokens });
        await flushPromises();
    };

    const rejectParse = async (index: number) => {
        requests[index].reject(new Error('Parser unavailable'));
        await flushPromises();
    };

    const getEditor = (): TextEditor =>
        wrapper!.findComponent({ name: 'mt-text-editor-toolbar' }).props('editor') as TextEditor;

    it('shows initial loading and a translated error with Retry without emitting or opening an unparsed editor', async () => {
        const field = mountField();
        expect(field.find('[role="status"]').text()).toContain('mapping.loadingText');
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);

        await rejectParse(0);

        expect(field.find('[role="status"]').exists()).toBe(false);
        expect(field.find('[role="alert"]').text()).toContain('mapping.parseError');
        expect(field.find('[role="alert"] button').text()).toContain('mapping.retryAction');
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);
        expect(field.emitted('update:value')).toBeUndefined();
        expect((field.props() as { value: string }).value).toBe(value);

        await field.find('[role="alert"] button').trigger('click');
        expect(requests[1].value).toBe(value);
        expect(field.find('[role="status"]').exists()).toBe(true);
        await resolveParse(1, tokens);

        expect(field.find('[role="alert"]').exists()).toBe(false);
        expect(field.find('.sw-experience-studio-mapping-token').text()).toBe('category.description');
        expect(field.find('.mt-text-editor__gate').exists()).toBe(false);
        expect(field.emitted('update:value')).toBeUndefined();
    });

    it('keeps editing blocked after a failed retry and retries the latest external value', async () => {
        const field = mountField();
        await rejectParse(0);
        await field.find('[role="alert"] button').trigger('click');
        await rejectParse(1);
        expect(field.find('[role="alert"]').exists()).toBe(true);
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);

        await field.setProps({ value: '<p>Another element</p>' });
        await rejectParse(2);
        await field.find('[role="alert"] button').trigger('click');
        expect(requests[3].value).toBe('<p>Another element</p>');
        await resolveParse(3);

        expect(field.find('.mt-text-editor__content-editor').text()).toBe('Another element');
        expect(field.emitted('update:value')).toBeUndefined();
    });

    it('preserves chips, text, the editor instance, and selection on a background failure and retry', async () => {
        const field = mountField();
        await resolveParse(0, tokens);
        const editor = getEditor();
        editor.commands.setTextSelection(4);
        editor.commands.insertContent('typed');
        await flushPromises();
        const html = editor.getHTML();
        const selection = { from: editor.state.selection.from, to: editor.state.selection.to };
        const emitted = field.emitted('update:value');
        const latestValue = emitted![emitted!.length - 1][0];
        expect(field.find('.sw-experience-studio-mapping-token').exists()).toBe(true);

        jest.advanceTimersByTime(800);
        expect(requests[1].value).toBe(latestValue);
        await rejectParse(1);

        expect(field.find('[role="alert"]').exists()).toBe(true);
        expect(getEditor()).toBe(editor);
        expect(editor.getHTML()).toBe(html);
        expect(editor.state.selection.from).toBe(selection.from);
        expect(editor.state.selection.to).toBe(selection.to);
        expect(field.find('.sw-experience-studio-mapping-token').exists()).toBe(true);

        await field.find('[role="alert"] button').trigger('click');
        expect(requests[2].value).toBe(latestValue);
        await resolveParse(2, tokens);

        expect(field.find('[role="alert"]').exists()).toBe(false);
        expect(getEditor()).toBe(editor);
        expect(editor.getHTML()).toBe(html);
        expect(editor.state.selection.from).toBe(selection.from);
        expect(field.emitted('update:value')).toHaveLength(emitted!.length);
    });

    it('keeps ordinary typing enabled while its background parse is pending', async () => {
        const field = mountField();
        await resolveParse(0, tokens);
        const editor = getEditor();
        editor.commands.setTextSelection(4);
        editor.commands.insertContent('first');
        jest.advanceTimersByTime(800);
        editor.commands.insertContent('second');
        await flushPromises();

        expect(getEditor()).toBe(editor);
        expect(field.findComponent({ name: 'mt-text-editor' }).props('disabled')).toBe(false);
        expect(editor.getHTML()).toContain('firstsecond');
        await rejectParse(1);
        expect(field.find('[role="alert"]').exists()).toBe(false);
        jest.advanceTimersByTime(800);
        expect(requests[2].value).toContain('firstsecond');
        await resolveParse(2, tokens);
        expect(editor.getHTML()).toContain('firstsecond');
    });

    it('blocks stale edits immediately after undo and keeps the restored value on parse failure', async () => {
        const field = mountField();
        await resolveParse(0, tokens);
        const oldEditor = field.findComponent({ name: 'mt-text-editor' });
        const editor = getEditor();
        editor.commands.insertContent('Local edit');
        const emittedCount = field.emitted('update:value')!.length;

        const restoredValue = '<p>Restored by undo</p>';
        await field.setProps({ value: restoredValue });
        expect(requests[1].value).toBe(restoredValue);
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);
        (oldEditor.vm as ComponentPublicInstance).$emit('update:modelValue', '<p>Stale typing</p>');
        expect(field.emitted('update:value')).toHaveLength(emittedCount);
        jest.advanceTimersByTime(800);
        expect(requests).toHaveLength(2);
        await rejectParse(1);
        expect(field.find('[role="alert"]').exists()).toBe(true);
        expect((field.props() as { value: string }).value).toBe(restoredValue);

        await field.find('[role="alert"] button').trigger('click');
        await resolveParse(2);
        expect(field.find('.mt-text-editor__content-editor').text()).toBe('Restored by undo');
        expect(field.emitted('update:value')).toHaveLength(emittedCount);
    });

    it.each([
        'resolve',
        'reject',
    ])('ignores a stale %s while a newer external value is loading', async (outcome) => {
        const field = mountField();
        await field.setProps({ value: '<p>Current text</p>' });
        if (outcome === 'resolve') {
            await resolveParse(0, tokens);
        } else {
            await rejectParse(0);
        }

        expect(field.find('[role="status"]').exists()).toBe(true);
        expect(field.find('[role="alert"]').exists()).toBe(false);
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);
        await resolveParse(1);
        expect(field.find('.mt-text-editor__content-editor').text()).toBe('Current text');
    });

    it('does not replace a newer successful document with an older response', async () => {
        const field = mountField();
        await field.setProps({ value: '<p>Current text</p>' });
        await resolveParse(1);
        await resolveParse(0, tokens);

        expect(field.find('.mt-text-editor__content-editor').text()).toBe('Current text');
        expect(field.find('[role="alert"]').exists()).toBe(false);
    });

    it('shows retry feedback for a response with an unexpected revision', async () => {
        const field = mountField();
        requests[0].resolve({ revision: 99, tokens });
        await flushPromises();

        expect(field.find('[role="status"]').exists()).toBe(false);
        expect(field.find('[role="alert"]').exists()).toBe(true);
        expect(field.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);
    });
});
