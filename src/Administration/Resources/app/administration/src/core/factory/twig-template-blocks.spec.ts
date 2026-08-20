/**
 * @sw-package framework
 */

import { analyzeTwigTemplateBlocks } from 'src/core/factory/twig-template-blocks';

describe('core/factory/twig-template-blocks.ts', () => {
    describe('analyzeTwigTemplateBlocks', () => {
        it('collects top-level and nested block names', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `{% block outer %}<div>{% block inner %}<span></span>{% endblock %}</div>{% endblock %}`,
            );

            expect(analysis.blockNames).toEqual([
                'outer',
                'inner',
            ]);
        });

        it('reports no unsafe blocks for a conditional chain that stays inside one block', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `{% block only %}<div v-if="a"></div><div v-else></div>{% endblock %}`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual([]);
        });

        it('reports both blocks when a conditional chain crosses a block boundary', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `{% block first %}<div v-if="a"></div>{% endblock %}{% block second %}<div v-else></div>{% endblock %}`,
            );

            expect(Array.from(analysis.unsafeBlockNames).sort()).toEqual([
                'first',
                'second',
            ]);
        });

        it('reports a block whose chain continues in content outside every block', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `<div><div v-if="a"></div>{% block tail %}<div v-else></div>{% endblock %}</div>`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual(['tail']);
        });

        it('reports a nested block that continues its parent block chain', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `{% block outer %}<div v-if="a"></div>{% block inner %}<div v-else></div>{% endblock %}{% endblock %}`,
            );

            expect(Array.from(analysis.unsafeBlockNames).sort()).toEqual([
                'inner',
                'outer',
            ]);
        });

        it('keeps a deeply nested chain that stays inside one block safe', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `{% block only %}<div><span v-if="a"></span><span v-else-if="b"></span><span v-else></span></div>{% endblock %}`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual([]);
        });

        it('reports an empty block that sits between two cases of a chain', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `<div><div v-if="a"></div>{% block gap %}{% endblock %}<div v-else></div></div>`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual(['gap']);
        });

        it('keeps an empty block trailing a finished chain safe', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `<div><div v-if="a"></div><div v-else></div>{% block tail %}{% endblock %}</div>`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual([]);
        });

        it('keeps an empty block in front of a chain safe', () => {
            const analysis = analyzeTwigTemplateBlocks(
                'sw-product-detail',
                `<div>{% block head %}{% endblock %}<div v-if="a"></div><div v-else></div></div>`,
            );

            expect(Array.from(analysis.unsafeBlockNames)).toEqual([]);
        });

        it('returns an empty analysis and warns for an unparsable template', () => {
            jest.spyOn(console, 'warn').mockImplementation(() => {});

            const analysis = analyzeTwigTemplateBlocks('sw-product-detail', '{% block broken %}');

            expect(analysis.blockNames).toEqual([]);
            expect(console.warn).toHaveBeenCalledWith(
                expect.stringContaining('Failed to parse the Twig template of "sw-product-detail"'),
                expect.anything(),
            );
        });
    });
});
