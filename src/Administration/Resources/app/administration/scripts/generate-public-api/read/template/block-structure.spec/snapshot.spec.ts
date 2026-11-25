/**
 * @sw-package framework
 *
 * How the snapshot shows the context of a block and the crossings of a template. `compiler.spec.ts` checks
 * that Vue sees the same for every template of the Administration.
 */
import type { BlockContext } from '../../../model/blocks';
import type { ComponentUnit } from '../../../model/model';
import { snapshotOf } from '../../../snapshot/snapshot';
import type { SourceTree } from '../../../source/source-tree';
import { CARD_FILE, exampleTree, MAJOR, OPTIONS, treeOf } from '../../../testing/example-components';
import { buildModel } from '../../build-model';

const FOLDER = 'src/module/sw-example/component/sw-example-card';

function unit(tree: SourceTree, name: string): ComponentUnit {
    const found = buildModel(tree, OPTIONS).components.find((candidate) => candidate.name === name);

    if (found === undefined) {
        throw new Error(`${name} is not in the model.`);
    }

    return found;
}

/** The lines of a section of the report of a component, without their indentation. */
function section(heading: string, tree: SourceTree, name = 'sw-example-card'): string[] {
    const report = snapshotOf(tree, OPTIONS).files[`module/sw-example/component/${name}.api.md`];
    const [, lines = ''] = new RegExp(`\\n${heading}\\n([\\s\\S]*?)\\n(?:\\n|\`\`\`)`).exec(report) ?? [];

    return lines === '' ? [] : lines.split('\n').map((line) => line.trim());
}

/** A section of the tile when its template is given, otherwise of the card. */
function sectionOf(heading: string, card: string, tile?: string): string[] {
    return section(heading, exampleTree({ card, tile }), tile === undefined ? 'sw-example-card' : 'sw-example-tile');
}

function blockLines(card: string, tile?: string): string[] {
    return sectionOf('blocks', card, tile);
}

function crossingLines(card: string, tile?: string): string[] {
    return sectionOf('crossings', card, tile);
}

function contextOf(block: string, card: string): BlockContext {
    const found = unit(exampleTree({ card }), 'sw-example-card').blocks.find(({ name }) => name === block);

    if (found === undefined) {
        throw new Error(`${block} is no block of the card.`);
    }

    return found.context;
}

describe('scripts/generate-public-api/read/template/block-structure in the snapshot', () => {
    describe('a block after a v-if chain', () => {
        it('marks a block that follows an element with v-if or v-else-if', () => {
            expect(
                blockLines(`
                    <div v-if="isLoading"></div>
                    {% block sw_example_card_error %}{% endblock %}
                    <div v-else-if="hasError"></div>
                    {% block sw_example_card_empty %}{% endblock %}
                `),
            ).toEqual([
                'sw_example_card_empty  [after v-if]',
                'sw_example_card_error  [after v-if]',
            ]);
        });

        it('keeps the conditions of the chain so far, for the break check', () => {
            const template = `
                <div
                    v-if="isLoading
                        && !hasError"
                ></div>
                <div v-else-if="hasError"></div>
                {% block sw_example_card_empty %}{% endblock %}
            `;

            expect(contextOf('sw_example_card_empty', template).afterIf).toEqual([
                'isLoading && !hasError',
                'hasError',
            ]);
        });

        it.each([
            ['starts with a v-else', '<p v-else></p>', true],
            ['starts with a v-else-if', '<p v-else-if="isEmpty"></p><p v-else></p>', true],
            [
                'starts with a block that starts with a v-else',
                '{% block sw_example_card_text %}<p v-else></p>{% endblock %}',
                true,
            ],
            ['is empty', '', false],
            ['starts with an element without a condition', '<p></p><p v-else></p>', false],
            ['starts with a v-if of its own', '<p v-if="isEmpty"></p><p v-else></p>', false],
            ['holds an empty block before the v-else', '{% block sw_example_card_text %}{% endblock %}<p v-else></p>', true],
        ])('knows whether a block continues the chain it follows when it %s', (_case, content, continuesChain) => {
            const template = `<div v-if="isLoading"></div>{% block sw_example_card_empty %}${content}{% endblock %}`;

            expect(contextOf('sw_example_card_empty', template).continuesChain).toBe(continuesChain);
        });

        it('does not take the v-else behind an empty block as the content of that block', () => {
            const template = '<div v-if="isLoading"></div>{% block sw_example_card_empty %}{% endblock %}<p v-else></p>';

            expect(contextOf('sw_example_card_empty', template).continuesChain).toBe(false);
        });

        it.each([
            ['a v-else that closes the chain', '<div v-if="isLoading"></div><div v-else></div>'],
            ['an element without a condition', '<div v-if="isLoading"></div><hr>'],
            ['text', '<div v-if="isLoading"></div>Loaded'],
            ['an interpolation', '<div v-if="isLoading"></div>{{ title }}'],
            ['nothing', ''],
        ])('does not mark a block after %s', (_case, before) => {
            expect(blockLines(`${before}{% block sw_example_card_empty %}{% endblock %}`)).toEqual([
                'sw_example_card_empty',
            ]);
        });

        it('looks through whitespace, comments and the tags of other blocks', () => {
            expect(
                blockLines(`
                    {% block sw_example_card_loader %}<div v-if="isLoading"></div>{% endblock %}
                    <!-- a comment -->
                    {# a Twig comment #}
                    {% block sw_example_card_body %}{% block sw_example_card_text %}{% endblock %}{% endblock %}
                `),
            ).toEqual([
                'sw_example_card_body  [after v-if]',
                'sw_example_card_text  [after v-if]',
                'sw_example_card_loader',
            ]);
        });

        it('starts a new chain inside an element', () => {
            expect(blockLines('<div v-if="isLoading">{% block sw_example_card_loader %}{% endblock %}</div>')).toEqual([
                'sw_example_card_loader',
            ]);
        });

        it('does not count a conditional slot, which only another slot can follow', () => {
            expect(
                blockLines(`
                    <sw-page>
                        <template v-if="isLoading" #header></template>
                        {% block sw_example_card_actions %}{% endblock %}
                    </sw-page>
                `),
            ).toEqual(['sw_example_card_actions']);
        });

        it('lists a block once per context when the template declares it in different places', () => {
            expect(
                blockLines(`
                    <template v-if="${MAJOR}"><div v-if="isLoading"></div>{% block sw_example_card_body %}{% endblock %}</template>
                    <template v-else>{% block sw_example_card_body %}{% endblock %}</template>
                `),
            ).toEqual([
                'sw_example_card_body  [until v6.8.0]',
                'sw_example_card_body  [after v-if] [from v6.8.0]',
            ]);
        });
    });

    describe('the template variables of a block', () => {
        it.each([
            [
                'a v-for alias',
                '<li v-for="item in items">',
                '</li>',
                ['item'],
            ],
            [
                'the index of a v-for',
                '<li v-for="(item, index) in items">',
                '</li>',
                ['index', 'item'],
            ],
            [
                'the key and index of a v-for over an object',
                '<li v-for="(value, key, index) in entity">',
                '</li>',
                ['index', 'key', 'value'],
            ],
            [
                'a destructured v-for alias',
                '<li v-for="{ id, name: label } in items">',
                '</li>',
                ['id', 'label'],
            ],
            [
                'a slot prop',
                '<sw-grid><template #column="slotProps">',
                '</template></sw-grid>',
                ['slotProps'],
            ],
            [
                'destructured slot props',
                '<sw-grid><template #column="{ item, column: { label } }">',
                '</template></sw-grid>',
                ['item', 'label'],
            ],
            [
                'the props of v-slot on a component',
                '<sw-grid v-slot="{ item }">',
                '</sw-grid>',
                ['item'],
            ],
            [
                'the props of a slot-scope attribute',
                '<sw-grid><template slot="column" slot-scope="{ item }">',
                '</template></sw-grid>',
                ['item'],
            ],
        ])('lists %s', (_case, open, close, variables) => {
            expect(blockLines(`${open}{% block sw_example_card_item %}{% endblock %}${close}`)).toEqual([
                `sw_example_card_item  [scope: ${variables.join(', ')}]`,
            ]);
        });

        it('keeps where a variable comes from, for the break check', () => {
            const template = `
                <sw-data-grid>
                    <template #actions="{ item }">
                        <li v-for="option in item.options">{% block sw_example_card_option %}{% endblock %}</li>
                    </template>
                </sw-data-grid>
            `;

            expect(contextOf('sw_example_card_option', template).scope).toEqual([
                { name: 'item', kind: 'slot', source: 'sw-data-grid#actions' },
                { name: 'option', kind: 'v-for', source: 'item.options' },
            ]);
        });

        it('names the default slot when the slot has no name', () => {
            const template = '<sw-grid v-slot="{ item }">{% block sw_example_card_item %}{% endblock %}</sw-grid>';

            expect(contextOf('sw_example_card_item', template).scope).toEqual([
                { name: 'item', kind: 'slot', source: 'sw-grid#default' },
            ]);
        });

        it('keeps the expression of a dynamic slot name in brackets', () => {
            const template =
                '<sw-grid><template #[slotName]="{ item }">{% block sw_example_card_item %}{% endblock %}</template></sw-grid>';

            expect(contextOf('sw_example_card_item', template).scope).toEqual([
                { name: 'item', kind: 'slot', source: 'sw-grid#[slotName]' },
            ]);
        });

        it('takes the inner variable when two elements declare the same name', () => {
            const template = `
                <ul v-for="item in groups">
                    <li v-for="item in item.children">{% block sw_example_card_item %}{% endblock %}</li>
                </ul>
            `;

            expect(contextOf('sw_example_card_item', template).scope).toEqual([
                { name: 'item', kind: 'v-for', source: 'item.children' },
            ]);
        });

        it('gives a block the variables of the blocks around it', () => {
            expect(
                blockLines(`
                    <li v-for="item in items">
                        {% block sw_example_card_item %}{% block sw_example_card_item_label %}{% endblock %}{% endblock %}
                    </li>
                    {% block sw_example_card_footer %}{% endblock %}
                `),
            ).toEqual([
                'sw_example_card_footer',
                'sw_example_card_item  [scope: item]',
                'sw_example_card_item_label  [scope: item]',
            ]);
        });

        it('reads the variables around a <sw-block> element', () => {
            const files = {
                'src/module/sw-example/index.js':
                    "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                [`${FOLDER}/index.ts`]: "export { default } from './sw-example-card.vue';",
                [`${FOLDER}/sw-example-card.vue`]: [
                    '<template>',
                    '    <ul>',
                    '        <sw-block v-for="item in items" name="sw_example_card_item">',
                    '            <sw-block name="sw_example_card_item_label">{{ item.label }}</sw-block>',
                    '        </sw-block>',
                    '    </ul>',
                    '</template>',
                    '',
                    '<script setup lang="ts"></script>',
                ].join('\n'),
            };

            expect(section('blocks', treeOf(files))).toEqual([
                'sw_example_card_item  [scope: item]',
                'sw_example_card_item_label  [scope: item]',
            ]);
        });
    });

    describe('crossings', () => {
        it('lists an element that opens in one block and closes in another', () => {
            expect(
                crossingLines(`
                    {% block sw_example_card %}
                    <div>
                        {% block sw_example_card_body %}
                    </div>
                    {% endblock %}
                    {% endblock %}
                `),
            ).toEqual(['<div>  sw_example_card -> sw_example_card_body']);
        });

        it('names the template outside of every block (root)', () => {
            expect(
                crossingLines(`
                    <div>
                        {% block sw_example_card_body %}
                    </div>
                    {% endblock %}
                    {% block sw_example_card_footer %}<footer>{% endblock %}</footer>
                `),
            ).toEqual([
                '<div>  (root) -> sw_example_card_body',
                '<footer>  sw_example_card_footer -> (root)',
            ]);
        });

        it('lists one crossing for an element that spans several blocks', () => {
            expect(
                crossingLines(`
                    {% block sw_example_card_header %}<div>{% endblock %}
                    {% block sw_example_card_body %}{% endblock %}
                    {% block sw_example_card_footer %}</div>{% endblock %}
                `),
            ).toEqual(['<div>  sw_example_card_header -> sw_example_card_footer']);
        });

        it('lists no crossing for an element that holds whole blocks', () => {
            expect(
                crossingLines('<div>{% block sw_example_card_body %}<br><img src="a.png" />{% endblock %}</div>'),
            ).toEqual([]);
        });

        it('lists a v-if chain that continues in another block', () => {
            expect(
                crossingLines(`
                    {% block sw_example_card_title %}<h2 v-if="title"></h2>{% endblock %}
                    {% block sw_example_card_subtitle %}<h3 v-else-if="subtitle"></h3>{% endblock %}
                    {% block sw_example_card_fallback %}<h4 v-else></h4>{% endblock %}
                `),
            ).toEqual([
                'v-if  sw_example_card_subtitle -> sw_example_card_fallback',
                'v-if  sw_example_card_title -> sw_example_card_subtitle',
            ]);
        });

        it('lists a chain that continues outside of the block it starts in', () => {
            expect(
                crossingLines(`
                    {% block sw_example_card %}
                    {% block sw_example_card_title %}<h2 v-if="title"></h2>{% endblock %}
                    <h3 v-else></h3>
                    {% endblock %}
                `),
            ).toEqual(['v-if  sw_example_card_title -> sw_example_card']);
        });

        it('lists no crossing when the block of the earlier member holds the block of the later one', () => {
            expect(
                crossingLines(`
                    {% block sw_example_card %}
                    <h2 v-if="title"></h2>
                    {% block sw_example_card_fallback %}<h3 v-else></h3>{% endblock %}
                    {% endblock %}
                `),
            ).toEqual([]);
        });

        it('marks a crossing between blocks that exist in one flag state only', () => {
            expect(
                crossingLines(`
                    <template v-if="${MAJOR}">
                        {% block sw_example_card_header %}<div>{% endblock %}
                        {% block sw_example_card_footer %}</div>{% endblock %}
                    </template>
                `),
            ).toEqual(['<div>  sw_example_card_header -> sw_example_card_footer  [from v6.8.0]']);
        });

        it('keeps the line of the element, for the break check', () => {
            const template = [
                '{% block sw_example_card %}',
                '{# a comment',
                '   over two lines #}',
                '<div>',
                '    {% block sw_example_card_body %}',
                '</div>',
                '{% endblock %}',
                '{% endblock %}',
            ].join('\n');

            expect(unit(exampleTree({ card: template }), 'sw-example-card').crossings.map(({ at }) => at)).toEqual([
                { file: CARD_FILE, line: 4 },
            ]);
        });
    });

    describe('an extend child', () => {
        const CARD = `
            {% block sw_example_card %}
            <ul>
                <li v-for="item in items">
                    <span v-if="item.icon"></span>
                    {% block sw_example_card_item %}<b v-else>{{ item.label }}</b>{% endblock %}
                </li>
            </ul>
            {% block sw_example_card_footer %}{% endblock %}
            {% endblock %}
        `;

        it('gives an inherited block the context of its place in the parent', () => {
            expect(blockLines(CARD, '{% block sw_example_card_footer %}{% endblock %}')).toEqual([
                'sw_example_card  [inherited]',
                'sw_example_card_footer  [replaced]',
                'sw_example_card_item  [inherited] [after v-if] [scope: item]',
            ]);
        });

        it('gives a block the child adds the context of the place it renders in', () => {
            expect(
                blockLines(
                    CARD,
                    '{% block sw_example_card_item %}{% parent %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}',
                ),
            ).toEqual([
                'sw_example_card  [inherited]',
                'sw_example_card_footer  [inherited]',
                'sw_example_card_item  [keepsParent] [after v-if] [scope: item]',
                'sw_example_tile_badge  [scope: item]',
            ]);
        });

        it('continues a chain of the parent content behind {% parent %}', () => {
            expect(
                blockLines(
                    '{% block sw_example_card %}<div v-if="isLoading"></div>{% endblock %}',
                    '{% block sw_example_card %}{% parent %}{% block sw_example_tile_empty %}{% endblock %}{% endblock %}',
                ),
            ).toEqual([
                'sw_example_card  [keepsParent]',
                'sw_example_tile_empty  [after v-if]',
            ]);
        });

        it('inherits a crossing and drops it with the block the child replaces', () => {
            const card = `
                {% block sw_example_card %}
                {% block sw_example_card_header %}<div>{% endblock %}
                {% block sw_example_card_footer %}</div>{% endblock %}
                {% endblock %}
            `;

            expect(crossingLines(card, '{% block sw_example_card %}{% parent %}{% endblock %}')).toEqual([
                '<div>  sw_example_card_header -> sw_example_card_footer',
            ]);
            expect(crossingLines(card, '{% block sw_example_card %}<p></p>{% endblock %}')).toEqual([]);
        });

        it('lists a crossing that the template of the child creates', () => {
            expect(
                crossingLines(
                    '{% block sw_example_card %}{% endblock %}',
                    '{% block sw_example_card %}<div>{% block sw_example_tile_body %}</div>{% endblock %}{% endblock %}',
                ),
            ).toEqual(['<div>  sw_example_card -> sw_example_tile_body']);
        });
    });
});
