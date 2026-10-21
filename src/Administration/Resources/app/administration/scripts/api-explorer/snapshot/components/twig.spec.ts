/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import { parseTemplate } from './twig';

const FILE = 'src/component/sw-example/sw-example.html.twig';

function parse(text: string, lineOffset = 0) {
    return parseTemplate({ file: FILE, text, lineOffset });
}

describe('parseTemplate', () => {
    it('builds the tree with parents, depths and keys', () => {
        const { blocks, diagnostics } = parse(
            [
                '{% block a %}',
                '    {% block b %}{% block c %}x{% endblock %}{% endblock %}',
                '    {% block b %}y{% endblock %}',
                '{% endblock %}',
                '{% block a %}z{% endblock %}',
            ].join('\n'),
        );

        expect(blocks.map(({ name, key, parent, depth }) => ({ name, key, parent, depth }))).toEqual([
            { name: 'a', key: 'a', parent: null, depth: 0 },
            { name: 'b', key: 'a/b', parent: 'a', depth: 1 },
            { name: 'c', key: 'a/b/c', parent: 'b', depth: 2 },
            { name: 'b', key: 'a/b~2', parent: 'a', depth: 1 },
            { name: 'a', key: 'a~2', parent: null, depth: 0 },
        ]);
        expect(blocks.map(({ location }) => location?.line)).toEqual([
            1,
            2,
            2,
            3,
            5,
        ]);
        expect(diagnostics).toEqual([]);
    });

    it('supports whitespace control and named endblock tags', () => {
        const { blocks, diagnostics } = parse('{%- block a -%}\n    <b>x</b>\n{%- endblock a -%}');

        expect(blocks).toMatchObject([{ name: 'a', content: '<b>x</b>' }]);
        expect(diagnostics).toEqual([]);
    });

    it('ignores blocks inside Twig comments and raw sections', () => {
        const { blocks } = parse(
            [
                '{# {% block commented %}{% endblock %} #}',
                '{% raw %}{% block raw %}{# not a comment {% endraw %}',
                '{% verbatim %}{% block verbatim %}{% endverbatim %}',
                '{% block real %}x{% endblock %}',
            ].join('\n'),
        );

        expect(blocks.map(({ name }) => name)).toEqual(['real']);
    });

    it('keeps the own markup dedented and hashes it with collapsed whitespace', () => {
        const { blocks } = parse(
            [
                '<div>',
                '    {% block outer %}',
                '        <ul>',
                '            {% block inner %}',
                '                <li>1</li>',
                '            {% endblock %}',
                '        </ul>',
                '    {% endblock %}',
                '</div>',
            ].join('\n'),
        );
        const outer = blocks[0].content;

        expect(outer).toBe('<ul>\n    {% block inner %}{% endblock %}\n</ul>');
        expect(blocks[0].contentHash).toBe(
            createHash('sha1').update('<ul> {% block inner %}{% endblock %} </ul>').digest('hex'),
        );
        expect(blocks[1].content).toBe('<li>1</li>');

        const reformatted = parse('{% block outer %}<ul>    {% block inner %}<li>1</li>{% endblock %} </ul>{% endblock %}');

        expect(reformatted.blocks[0].contentHash).toBe(blocks[0].contentHash);
    });

    it('reads a deprecation comment directly before the block or as its first content', () => {
        const { blocks } = parse(
            [
                '{# @deprecated tag:v6.8.0 - Use block b instead #}',
                '{% block a %}x{% endblock %}',
                '{% block b %}',
                '    {#',
                '     * @deprecated tag:v6.9.0 - Will be removed',
                '     #}',
                '    y',
                '{% endblock %}',
                '{# @deprecated tag:v6.8.0 - Too far away #}',
                'text',
                '{% block c %}{% endblock %}',
            ].join('\n'),
        );

        expect(blocks.map(({ deprecation }) => deprecation)).toEqual([
            { version: 'v6.8.0', message: 'Use block b instead' },
            { version: 'v6.9.0', message: 'Will be removed' },
            null,
        ]);
    });

    it('reports unbalanced block tags', () => {
        expect(parse('{% block a %}x{% endblock b %}{% endblock %}{% block c %}').diagnostics).toEqual([
            { file: FILE, message: 'endblock b in line 1 closes block a.' },
            { file: FILE, message: 'Unexpected endblock in line 1.' },
            { file: FILE, message: 'Block c in line 1 is not closed.' },
        ]);
    });

    it('lists static slots and $emit calls outside comments', () => {
        const { slots, emits } = parse(
            [
                '<slot></slot>',
                '<slot name="header" :item="item" />',
                '<slot v-bind:name="dynamic"></slot><slot :name=\'other\'/>',
                '{# <slot name="commented"></slot> #}',
                '<!-- <slot name="html-comment"></slot> -->',
                '<button @click="$emit(\'close\', true)" @blur="$emit(\'close\')"></button>',
            ].join('\n'),
            10,
        );

        expect(slots.map(({ name, location }) => [name, location?.line])).toEqual([
            ['default', 11],
            ['header', 12],
        ]);
        expect(emits.map(({ name, location }) => [name, location?.line])).toEqual([['close', 16]]);
    });
});
