import type { ContentSystemInlineMappingParseResponse } from 'src/core/service/api/content-system-mapping-candidate.api.service';
import { mount } from '@vue/test-utils';
import inlineTextFieldComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-inline-text-field - editor initialization', () => {
    let wrapper: ReturnType<typeof mount> | null = null;
    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        jest.restoreAllMocks();
    });

    it('waits for the initial token parse and renders an unavailable mapping as a token chip', async () => {
        const value = '{{map:category.description}}';
        let resolveParse!: (response: ContentSystemInlineMappingParseResponse) => void;
        const parseInlineMappingTokens = jest.fn(
            () =>
                new Promise<ContentSystemInlineMappingParseResponse>((resolve) => {
                    resolveParse = resolve;
                }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);

        wrapper = mount(
            inlineTextFieldComponent as never,
            {
                props: { value },
                global: {
                    stubs: {
                        'sw-text-editor-toolbar-button-link': true,
                    },
                },
            } as never,
        );

        expect(wrapper.findComponent({ name: 'mt-text-editor' }).exists()).toBe(false);

        resolveParse({
            revision: 1,
            tokens: [
                {
                    path: 'category.description',
                    match: '{{map:category.description}}',
                    canonicalToken: '{{map:category.description}}',
                    offset: 0,
                    insideMarkup: false,
                    malformed: false,
                },
            ],
        });
        await flushPromises();

        const editor = wrapper.find('.mt-text-editor');
        expect(editor.exists()).toBe(true);
        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(false);
        const mappingToken = wrapper.find('.sw-experience-studio-mapping-token');
        expect(mappingToken.text()).toBe('category.description');
        expect(wrapper.find('.mt-text-editor__content-editor').text()).not.toContain('{{map:category.description}}');
    });

    it('applies server spans and reloads the editor for an external value', async () => {
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
                    {
                        path: 'product.nmae',
                        match: '{{map:product.nmae}}',
                        canonicalToken: '{{map:product.nmae}}',
                        offset: 28,
                        insideMarkup: false,
                        malformed: false,
                    },
                ],
            }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);
        wrapper = mount(
            inlineTextFieldComponent as never,
            {
                props: { value: '<p>{{map:product.name}} and {{map:product.nmae}}</p>' },
                global: { stubs: { 'sw-text-editor-toolbar-button-link': true } },
            } as never,
        );
        await flushPromises();
        expect(wrapper.findAll('.sw-experience-studio-mapping-token').map((chip) => chip.text())).toEqual([
            'product.name',
            'product.nmae',
        ]);

        parseInlineMappingTokens.mockImplementation((_text: string, revision: number) =>
            Promise.resolve({ revision, tokens: [] }),
        );
        await wrapper.setProps({ value: '<p>Restored externally</p>' });
        await flushPromises();
        expect(wrapper.find('.mt-text-editor__content-editor').text()).toBe('Restored externally');
        expect(wrapper.find('.sw-experience-studio-mapping-token').exists()).toBe(false);
    });

    it.each([
        [
            'dotless',
            '{{map:product}}',
            'product',
        ],
        [
            'quoted',
            '{{map:x" onmouseover="alert(1)}}',
            'x" onmouseover="alert(1)',
        ],
    ])('leaves a malformed %s server token visible as text in the real editor', async (_case, value, path) => {
        let resolveParse!: (response: ContentSystemInlineMappingParseResponse) => void;
        const parseInlineMappingTokens = jest.fn(
            () =>
                new Promise<ContentSystemInlineMappingParseResponse>((resolve) => {
                    resolveParse = resolve;
                }),
        );
        jest.spyOn(Shopware, 'Service').mockReturnValue({ parseInlineMappingTokens } as never);

        wrapper = mount(
            inlineTextFieldComponent as never,
            {
                props: { value },
                global: { stubs: { 'sw-text-editor-toolbar-button-link': true } },
            } as never,
        );

        resolveParse({
            revision: 1,
            tokens: [
                {
                    path,
                    match: value,
                    canonicalToken: null,
                    offset: 0,
                    insideMarkup: false,
                    malformed: true,
                },
            ],
        });
        await flushPromises();

        expect(wrapper.find('.sw-experience-studio-mapping-token').exists()).toBe(false);
        expect(wrapper.find('.mt-text-editor__content-editor').text()).toContain(value);
        expect(wrapper.find('[onmouseover]').exists()).toBe(false);
        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(false);
    });
});
