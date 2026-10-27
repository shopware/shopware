import { mount } from '@vue/test-utils';
import inlineTextFieldComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-inline-text-field - editor initialization', () => {
    let wrapper: ReturnType<typeof mount> | null = null;
    let resolveParse:
        | ((response: {
              revision: number;
              tokens: {
                  path: string;
                  match: string;
                  canonicalToken: string;
                  offset: number;
                  insideMarkup: boolean;
                  malformed: boolean;
              }[];
          }) => void)
        | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        jest.restoreAllMocks();
    });

    it('waits for the initial token parse and renders an unavailable mapping as a token chip', async () => {
        const value = '{{map:category.description}}';
        const parseInlineMappingTokens = jest.fn(
            () =>
                new Promise<{
                    revision: number;
                    tokens: {
                        path: string;
                        match: string;
                        canonicalToken: string;
                        offset: number;
                        insideMarkup: boolean;
                        malformed: boolean;
                    }[];
                }>((resolve) => {
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

        resolveParse?.({
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
        const parseInlineMappingTokens = jest.fn(
            () =>
                new Promise<{
                    revision: number;
                    tokens: {
                        path: string;
                        match: string;
                        canonicalToken: string;
                        offset: number;
                        insideMarkup: boolean;
                        malformed: boolean;
                    }[];
                }>((resolve) => {
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

        resolveParse?.({
            revision: 1,
            tokens: [
                {
                    path,
                    match: value,
                    canonicalToken: value,
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
