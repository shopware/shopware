/**
 * @sw-package framework
 */

import {
    indexTwigBlocksFromTemplate,
    getBlockEntries,
    hasBlockEntries,
    resetBlockIndex,
} from 'src/core/factory/twig-block-index';

describe('core/factory/twig-block-index.ts', () => {
    afterEach(() => {
        jest.restoreAllMocks();
        resetBlockIndex();
    });

    describe('indexTwigBlocksFromTemplate', () => {
        it('indexes a single block from a Twig template', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block my_block %}<div class="content"></div>{% endblock %}
            `,
            );

            expect(hasBlockEntries('my_block')).toBe(true);
        });

        it('indexes multiple top-level blocks from a single template', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block block_a %}<div class="a"></div>{% endblock %}
                {% block block_b %}<div class="b"></div>{% endblock %}
            `,
            );

            expect(hasBlockEntries('block_a')).toBe(true);
            expect(hasBlockEntries('block_b')).toBe(true);
        });

        it('stores the component name and inner template on the block entry', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block comp_name_block %}<div class="inner"></div>{% endblock %}
            `,
            );

            const [entry] = getBlockEntries('comp_name_block');
            expect(entry.componentName).toBe('sw-product-detail');
            expect(entry.innerTemplate).toContain('class="inner"');
        });

        it('accumulates multiple entries for the same block name from separate calls', () => {
            indexTwigBlocksFromTemplate(
                'sw-plugin-a',
                `
                {% block shared_block %}<div class="a"></div>{% endblock %}
            `,
            );
            indexTwigBlocksFromTemplate(
                'sw-plugin-b',
                `
                {% block shared_block %}<div class="b"></div>{% endblock %}
            `,
            );

            const entries = getBlockEntries('shared_block');
            expect(entries).toHaveLength(2);
            expect(entries[0].componentName).toBe('sw-plugin-a');
            expect(entries[1].componentName).toBe('sw-plugin-b');
        });

        it('warns and ignores malformed Twig templates without throwing', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            expect(() => {
                indexTwigBlocksFromTemplate('sw-product-detail', '{% block unclosed <div {{ ');
            }).not.toThrow();

            expect(consoleWarn).toHaveBeenCalledWith(
                '[sw-block] Failed to parse Twig template for "sw-product-detail":',
                expect.anything(),
            );
        });

        it('does not index any block entries from a malformed template', () => {
            jest.spyOn(console, 'warn').mockImplementation(() => {});

            indexTwigBlocksFromTemplate('sw-product-detail', '{% block malformed_block <div {{ ');

            expect(hasBlockEntries('malformed_block')).toBe(false);
        });
    });

    describe('getBlockEntries', () => {
        it('returns an empty array for a block name that has never been indexed', () => {
            expect(getBlockEntries('nonexistent_block')).toEqual([]);
        });

        it('returns all entries for a known block name in registration order', () => {
            indexTwigBlocksFromTemplate('sw-a', `{% block order_block %}<div class="a"></div>{% endblock %}`);
            indexTwigBlocksFromTemplate('sw-b', `{% block order_block %}<div class="b"></div>{% endblock %}`);

            const entries = getBlockEntries('order_block');
            expect(entries[0].componentName).toBe('sw-a');
            expect(entries[1].componentName).toBe('sw-b');
        });
    });

    describe('hasBlockEntries', () => {
        it('returns false for a block name that has not been indexed', () => {
            expect(hasBlockEntries('unknown_block')).toBe(false);
        });
    });

    describe('resetBlockIndex', () => {
        it('clears all indexed blocks so that previously indexed blocks are no longer found', () => {
            indexTwigBlocksFromTemplate('sw-product-detail', `{% block reset_block %}<div></div>{% endblock %}`);
            expect(hasBlockEntries('reset_block')).toBe(true);

            resetBlockIndex();

            expect(hasBlockEntries('reset_block')).toBe(false);
        });

        it('allows re-indexing of the same block name after reset without accumulating old entries', () => {
            const template1 = `{% block reindex_block %}<div class="first"></div>{% endblock %}`;
            const template2 = `{% block reindex_block %}<div class="second"></div>{% endblock %}`;
            indexTwigBlocksFromTemplate('sw-product-detail', template1);
            resetBlockIndex();
            indexTwigBlocksFromTemplate('sw-product-detail', template2);

            const entries = getBlockEntries('reindex_block');
            expect(entries).toHaveLength(1);
            expect(entries[0].innerTemplate).toContain('class="second"');
        });
    });
});
