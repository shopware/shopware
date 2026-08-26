/**
 * @sw-package framework
 */

import { compile } from '@vue/compiler-dom';
import {
    indexTwigBlocksFromTemplate,
    getBlockEntries,
    hasBlockEntries,
    resetBlockIndex,
} from 'src/core/factory/twig-block-index';
import transformNativeLegacyBlockConditionals from 'src/core/factory/transform-legacy-block-conditionals';

describe('core/factory/twig-block-index.ts', () => {
    afterEach(() => {
        jest.restoreAllMocks();
        resetBlockIndex();
    });

    describe('indexTwigBlocksFromTemplate', () => {
        function options(segmentCaseIndex: number, isStartingCondition: boolean, renderOrderSegment: string): string {
            return `{ segmentCaseIndex: ${segmentCaseIndex}, isStartingCondition: ${isStartingCondition}, renderOrderSegment: '${renderOrderSegment}' }`;
        }

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

        it('stores legacy Twig parent/v-else overrides with native block condition helpers', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block legacy_else_block %}
                    {% parent %}
                    <div v-else class="legacy-else"></div>
                {% endblock %}
            `,
            );

            const [entry] = getBlockEntries('legacy_else_block');

            expect(entry.innerTemplate).toContain('<sw-block-parent />');
            expect(entry.innerTemplate).toContain(
                `v-if="$swLegacyBlockElse('legacy_else_block:0', ${options(0, false, 'shimExtension')})"`,
            );
            expect(entry.innerTemplate).not.toContain('v-else class="legacy-else"');
            expect(entry.legacyConditionCases).toEqual([
                {
                    chainKey: 'legacy_else_block:0',
                    caseCount: 1,
                    caseStartIndex: 0,
                },
            ]);
        });

        it('offsets legacy condition cases for chained plugin overrides of the same block', () => {
            indexTwigBlocksFromTemplate(
                'sw-plugin-one',
                `
                {% block shared_condition_block %}
                    {% parent %}
                    <div v-else-if="condition2" class="plugin-one"></div>
                {% endblock %}
            `,
            );
            indexTwigBlocksFromTemplate(
                'sw-plugin-two',
                `
                {% block shared_condition_block %}
                    {% parent %}
                    <div v-else class="plugin-two"></div>
                {% endblock %}
            `,
            );

            const [
                pluginOne,
                pluginTwo,
            ] = getBlockEntries('shared_condition_block');

            expect(pluginOne.innerTemplate).toContain(
                `v-if="$swLegacyBlockElseIf('shared_condition_block:0', condition2, ${options(0, false, 'shimExtension')})"`,
            );
            expect(pluginOne.legacyConditionCases).toEqual([
                {
                    chainKey: 'shared_condition_block:0',
                    caseCount: 1,
                    caseStartIndex: 0,
                },
            ]);
            expect(pluginTwo.innerTemplate).toContain(
                `v-if="$swLegacyBlockElse('shared_condition_block:0', ${options(1, false, 'shimExtension')})"`,
            );
            expect(pluginTwo.legacyConditionCases).toEqual([
                {
                    chainKey: 'shared_condition_block:0',
                    caseCount: 1,
                    caseStartIndex: 1,
                },
            ]);
        });

        it('rewrites Twig-started condition chains across separate overrides', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block twig_started_condition_block %}
                    {% parent %}
                    <h1 v-if="conditionFromPluginOne" class="plugin-one-condition">Plugin one</h1>
                {% endblock %}
            `,
            );
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block twig_started_condition_block %}
                    {% parent %}
                    <h1 v-else class="plugin-two-fallback">Plugin two fallback</h1>
                {% endblock %}
            `,
            );

            const [
                pluginOne,
                pluginTwo,
            ] = getBlockEntries('twig_started_condition_block');

            expect(pluginOne.innerTemplate).toContain(
                `v-if="$swLegacyBlockIf('twig_started_condition_block:0', conditionFromPluginOne, ${options(0, true, 'shimExtension')})"`,
            );
            expect(pluginOne.legacyConditionCases).toEqual([
                {
                    chainKey: 'twig_started_condition_block:0',
                    caseCount: 1,
                    caseStartIndex: 0,
                    startsChain: true,
                },
            ]);
            expect(pluginTwo.innerTemplate).toContain(
                `v-if="$swLegacyBlockElse('twig_started_condition_block:0', ${options(1, false, 'shimExtension')})"`,
            );
            expect(pluginTwo.legacyConditionCases).toEqual([
                {
                    chainKey: 'twig_started_condition_block:0',
                    caseCount: 1,
                    caseStartIndex: 1,
                },
            ]);
        });

        it('rebuilds indexed Twig condition chains with native continuation aliases', () => {
            indexTwigBlocksFromTemplate(
                'sw-product-detail',
                `
                {% block block_2 %}
                    <div v-else-if="conditionFromPlugin" class="plugin-two">plugin two</div>
                {% endblock %}
            `,
            );

            const nativeTemplate = transformNativeLegacyBlockConditionals(
                `
                <sw-block name="block_1">
                    <div v-if="condition1" class="one">one</div>
                </sw-block>

                <sw-block name="block_2">
                    <div v-else-if="condition2" class="two">two</div>
                </sw-block>
            `,
                'sw-product-detail',
            );
            const [entry] = getBlockEntries('block_2');

            expect(nativeTemplate).toBe(`
                <sw-block name="block_1">
                    <div v-if="$swLegacyBlockIf('block_1:0', condition1, ${options(0, true, 'defaultSlot')})" class="one">one</div>
                </sw-block>

                <sw-block name="block_2">
                    <div v-if="$swLegacyBlockElseIf('block_1:0', condition2, ${options(1, false, 'defaultSlot')})" class="two">two</div>
                </sw-block>
            `);
            expect(entry.innerTemplate).toBe(
                `                    <div v-if="$swLegacyBlockElseIf('block_1:0', conditionFromPlugin, ${options(0, false, 'shimExtension')})" class="plugin-two">plugin two</div>
                `,
            );
            expect(entry.legacyConditionCases).toEqual([
                {
                    chainKey: 'block_1:0',
                    caseCount: 1,
                    caseStartIndex: 0,
                },
            ]);
            expect(() => compile(entry.innerTemplate)).not.toThrow();
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
