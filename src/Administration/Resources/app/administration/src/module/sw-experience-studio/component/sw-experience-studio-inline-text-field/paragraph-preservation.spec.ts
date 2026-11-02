import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import type { Component } from 'vue';
import type { ContentSystemInlineMappingToken } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import inlineTextFieldComponent from './index';
import elementSettingsComponent from '../sw-experience-studio-element-settings';

type TextEditor = {
    commands: {
        setTextSelection: (position: number) => boolean;
        insertContent: (text: string) => boolean;
        deleteRange: (range: { from: number; to: number }) => boolean;
    };
};

describe('Experience Studio inline text paragraph preservation', () => {
    const token = '{{map:product.name}}';
    let wrapper: ReturnType<typeof mount> | null = null;

    const parsedValue = (value: string, revision: number) => {
        const offset = value.indexOf(token);
        const tokens: ContentSystemInlineMappingToken[] =
            offset === -1
                ? []
                : [
                      {
                          path: 'product.name',
                          match: token,
                          canonicalToken: token,
                          offset,
                          insideMarkup: false,
                          malformed: false,
                      },
                  ];

        return { revision, tokens };
    };

    beforeEach(() => {
        jest.useFakeTimers();
        const parseInlineMappingTokens = jest.fn((value: string, revision: number) =>
            Promise.resolve(parsedValue(value, revision)),
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

    const mountField = async (value: string) => {
        wrapper = mount(inlineTextFieldComponent as Component, {
            props: { value },
            global: { stubs: { 'sw-text-editor-toolbar-button-link': true } },
        });
        await flushPromises();

        return wrapper;
    };

    const editor = (): TextEditor =>
        wrapper!.findComponent({ name: 'mt-text-editor-toolbar' }).props('editor') as TextEditor;

    const latestValue = (): string => {
        const values = wrapper!.emitted('update:value')!;

        return values[values.length - 1][0] as string;
    };

    it.each([
        [
            'a bare token',
            token,
        ],
        [
            'an authored paragraph',
            `<p>${token}</p>`,
        ],
    ])('preserves %s through editing, parent echoes, and background parsing', async (_name, value) => {
        const field = await mountField(value);
        expect(field.find('.mt-text-editor__gate').exists()).toBe(false);
        expect(field.emitted('update:value')).toBeUndefined();
        const initialEditor = editor();

        initialEditor.commands.setTextSelection(2);
        initialEditor.commands.insertContent('x');
        await flushPromises();
        await field.setProps({ value: latestValue() });
        jest.advanceTimersByTime(800);
        await flushPromises();

        expect(editor()).toBe(initialEditor);
        initialEditor.commands.deleteRange({ from: 2, to: 3 });
        await flushPromises();

        expect(latestValue()).toBe(value);
    });

    it.each([
        [
            token,
            `<p>${token}</p>`,
        ],
        [
            `<p>${token}</p>`,
            token,
        ],
    ])('reloads paragraph ownership when an external value changes from %s to %s', async (initial, next) => {
        const field = await mountField(initial);
        await field.setProps({ value: next });
        await flushPromises();
        expect(field.emitted('update:value')).toBeUndefined();

        editor().commands.setTextSelection(2);
        editor().commands.insertContent('x');
        editor().commands.deleteRange({ from: 2, to: 3 });
        await flushPromises();

        expect(latestValue()).toBe(next);
    });

    it.each([
        token,
        `<p>${token}</p>`,
    ])('still clears the value when the author deletes all of %s', async (value) => {
        await mountField(value);

        editor().commands.deleteRange({ from: 1, to: 2 });
        await flushPromises();

        expect(latestValue()).toBe('');
    });

    it.each([
        [
            token,
            `<p>${token}</p>`,
        ],
        [
            `<p>${token}</p>`,
            token,
        ],
    ])('ignores wrapper ownership from a stale parse of %s after loading %s', async (older, current) => {
        const service = Shopware.Service('contentSystemMappingCandidateService');
        let resolveOlder = () => {};
        jest.spyOn(service, 'parseInlineMappingTokens').mockImplementationOnce(
            (value, revision) =>
                new Promise((resolve) => {
                    resolveOlder = () => resolve(parsedValue(value, revision));
                }),
        );
        const field = await mountField(older);
        await field.setProps({ value: current });
        await flushPromises();
        resolveOlder();
        await flushPromises();

        editor().commands.setTextSelection(2);
        editor().commands.insertContent('x');
        editor().commands.deleteRange({ from: 2, to: 3 });
        await flushPromises();

        expect(latestValue()).toBe(current);
    });

    it('isolates ownership when switching to an element with the same text as the last local edit', async () => {
        const fields = defineComponent({
            inheritAttrs: false,
            props: ['values'],
            components: { InlineField: inlineTextFieldComponent as Component },
            template: '<InlineField :value="values.text" />',
        });
        const initial = { id: 'first', component: 'Sw:Content:Text', properties: { text: token } };
        wrapper = mount(elementSettingsComponent as Component, {
            props: {
                selectedElementId: initial.id,
                selectedElement: initial,
                selectedElementType: { properties: { text: { type: 'string', inlineMappable: true } } },
                allowEdit: true,
            },
            global: {
                stubs: {
                    'sw-experience-studio-settings-fields': fields,
                    'sw-text-editor-toolbar-button-link': true,
                    'mt-tabs': true,
                },
            },
        });
        await flushPromises();
        const firstEditor = editor();
        firstEditor.commands.setTextSelection(2);
        firstEditor.commands.insertContent('x');
        await flushPromises();

        const text = `<p>${token}x</p>`;
        await wrapper.setProps({ selectedElement: { ...initial, properties: { text } } });
        await flushPromises();
        expect(editor()).toBe(firstEditor);

        await wrapper.setProps({
            selectedElementId: 'second',
            selectedElement: { ...initial, id: 'second', properties: { text } },
        });
        await flushPromises();
        expect(editor()).not.toBe(firstEditor);
        editor().commands.deleteRange({ from: 2, to: 3 });
        await flushPromises();

        expect(wrapper.findComponent(inlineTextFieldComponent as Component).emitted('update:value')).toEqual([
            [`<p>${token}</p>`],
        ]);
    });
});
