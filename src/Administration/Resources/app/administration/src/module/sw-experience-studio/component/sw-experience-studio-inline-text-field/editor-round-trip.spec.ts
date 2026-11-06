import { mount } from '@vue/test-utils';
import { tokensToEditorHtml } from '../../util/inline-mapping.util';
import { createMappingTokenNode } from '../../util/mapping-token-node';
import parserResponses from './fixtures/parser-responses.json';

/**
 * The one invariant that makes the inline mapping field usable: markup handed to `mt-text-editor` must survive being
 * parsed and re-serialized unchanged.
 *
 * The editor checks exactly that on mount and, when it fails, makes itself read-only behind a "review these changes"
 * gate — a guard against tiptap silently dropping markup, and not something a caller can switch off. So a failure
 * here is not cosmetic: it means the author cannot edit the text at all, on every page load, until they click through
 * a diff dialog.
 *
 * These mount the real editor rather than asserting on strings, because the failure modes come from tiptap's schema
 * and serializer rather than from our own code, and both bugs this pins were invisible at the string level.
 */
describe('module/sw-experience-studio/component/sw-experience-studio-inline-text-field - editor round trip', () => {
    const KNOWN = new Set(['category.description']);
    const wrappers: ReturnType<typeof mount>[] = [];

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        wrappers.length = 0;
    });

    const mountEditor = async (storedValue: string) => {
        const response = parserResponses.find((entry) => entry.text === storedValue);
        if (!response) throw new Error('Missing literal server response fixture');
        const tokens = response.tokens;
        const { html: modelValue } = tokensToEditorHtml(storedValue, tokens);

        const wrapper = mount(await wrapTestComponent('mt-text-editor', { sync: true }), {
            props: {
                modelValue,
                tipTapConfig: {
                    extensions: [
                        createMappingTokenNode({
                            resolveLabel: (path) => (KNOWN.has(path) ? 'Category description' : path),
                        }),
                    ],
                },
            },
            global: {
                stubs: {
                    'sw-text-editor-toolbar-button-link': true,
                },
            },
        });

        wrappers.push(wrapper);
        await flushPromises();

        return wrapper;
    };

    it.each([
        [
            'a text mapped as a whole, stored as one bare token',
            '{{map:category.description}}',
        ],
        [
            'a token in prose',
            '<p>The {{map:category.description}} explains it.</p>',
        ],
        [
            'a token wrapped in a paragraph',
            '<p>{{map:category.description}}</p>',
        ],
        [
            'two tokens in one paragraph',
            '<p>{{map:category.description}} / {{map:category.description}}</p>',
        ],
        [
            'a token in a heading',
            '<h2>{{map:category.description}}</h2>',
        ],
        // tiptap's list schema puts a paragraph inside every item, so this is the shape the editor itself writes.
        // `<li>` holding bare text gates regardless of mapping, which is pre-existing `mt-text-editor` behaviour.
        [
            'a token in a list item',
            '<ul><li><p>{{map:category.description}}</p></li></ul>',
        ],
        [
            'a parser-recognized token without a candidate label',
            '<p>{{map:category.nonsense}}</p>',
        ],
        [
            'an empty value',
            '',
        ],
        [
            'plain prose with no token at all',
            'Just words.',
        ],
    ])('does not lock the editor for %s', async (_name, storedValue) => {
        const wrapper = await mountEditor(storedValue);

        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(false);
    });

    it('shows the review gate when parsing changes the supplied markup', async () => {
        const wrapper = mount(await wrapTestComponent('mt-text-editor', { sync: true }), {
            props: { modelValue: '<p>Text<unsupported-element>lost markup</unsupported-element></p>' },
            global: { stubs: { 'sw-text-editor-toolbar-button-link': true } },
        });
        wrappers.push(wrapper);
        await flushPromises();
        expect(wrapper.find('.mt-text-editor__gate').exists()).toBe(true);
    });

    it('draws the mapped value as a chip with its catalogue label', async () => {
        const wrapper = await mountEditor('{{map:category.description}}');
        const chip = wrapper.find('.sw-experience-studio-mapping-token');

        expect(chip.exists()).toBe(true);
        expect(chip.text()).toBe('Category description');
    });

    it('keeps a parser-recognized token editable when the candidate catalogue has no label for it', async () => {
        const wrapper = await mountEditor('<p>{{map:category.nonsense}}</p>');

        const chip = wrapper.find('.sw-experience-studio-mapping-token');
        expect(chip.exists()).toBe(true);
        expect(chip.text()).toBe('category.nonsense');
    });
});
