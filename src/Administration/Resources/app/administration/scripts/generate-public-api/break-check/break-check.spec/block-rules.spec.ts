/**
 * @sw-package framework
 *
 * What each rule of the break check reports for a change to the blocks of a component. The fixtures in
 * `testing/__fixtures__/` hold the complete texts of the findings, these tests the cases around each rule.
 */
import { generateCurrentSnapshot } from '../../snapshot/committed-snapshot';
import { CARD_FILE, MAJOR } from '../../testing/example-components';
import { classifyChanges } from '../break-check';
import { check, summary } from './sources';

describe('scripts/generate-public-api/break-check/break-check block rules', () => {
    describe('changes that no override notices', () => {
        it('reports nothing for two snapshots of the same sources', () => {
            const template = `
                {% block sw_example_card %}
                <div v-if="isLoading"></div>
                <li v-for="item in items">{% block sw_example_card_item %}<b v-else></b>{% endblock %}</li>
                <div>{% block sw_example_card_body %}</div>{% endblock %}
                {% endblock %}
            `;

            expect(check(template, template)).toEqual([]);
        });

        it('reports nothing for the Administration compared with itself', () => {
            const { model } = generateCurrentSnapshot();

            expect(model.components.length).toBeGreaterThan(700);
            expect(classifyChanges(model, model)).toEqual([]);
        });

        it('reports nothing when blocks change their order under the same parent', () => {
            expect(
                check(
                    '{% block sw_example_card %}{% block sw_example_card_a %}A{% endblock %}{% block sw_example_card_b %}B{% endblock %}{% endblock %}',
                    '{% block sw_example_card %}{% block sw_example_card_b %}B{% endblock %}{% block sw_example_card_a %}A{% endblock %}{% endblock %}',
                ),
            ).toEqual([]);
        });

        it('reports nothing when the content of a block changes', () => {
            expect(
                check(
                    '{% block sw_example_card %}<h2 class="title">{{ title }}</h2>{% endblock %}',
                    '{% block sw_example_card %}<section><h3 class="title" :title="title">{{ headline }}</h3><p v-if="text"></p></section>{% endblock %}',
                ),
            ).toEqual([]);
        });

        it('reports nothing for a component that only one of the snapshots has', () => {
            const card = '{% block sw_example_card %}{% endblock %}';
            const tile = '{% block sw_example_card %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}';

            expect(check({ card }, { card, tile })).toEqual([]);
            expect(check({ card, tile }, { card })).toEqual([]);
        });
    });

    describe('blocks that come and go', () => {
        const BEFORE = '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}';

        it('reports a removed block as a break and names the block around it as the place', () => {
            const [finding, ...others] = check(BEFORE, '{% block sw_example_card %}\n\n{% endblock %}');

            expect(others).toEqual([]);
            expect(finding).toEqual({
                group: 'Break',
                rule: 'block_missing',
                element: 'component:sw-example-card/block:sw_example_card_title',
                states: ['current', 'major'],
                explanation: 'The block is gone, so plugin template overrides of sw_example_card_title render nothing.',
                fix: 'pattern "Removal". Keep the block, even empty, and mark it {# @deprecated tag:v6.8.0 - Will be removed. #}.',
                at: `${CARD_FILE}:1`,
            });
        });

        it('reports a new block as an addition', () => {
            expect(summary(check('{% block sw_example_card %}{% endblock %}', BEFORE))).toEqual([
                'Addition  block_added  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('suggests a rename when one new block takes the place of the removed one', () => {
            const findings = check(
                BEFORE,
                '{% block sw_example_card %}{% block sw_example_card_headline %}{% endblock %}{% endblock %}',
            );

            expect(summary(findings)).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  current, major',
                'Addition  block_added  sw-example-card/block:sw_example_card_headline  current, major',
            ]);
            expect(findings[0]).toMatchObject({
                possibleRename: 'sw_example_card_headline',
                fix: 'pattern "Rename". Keep sw_example_card_title and mark it {# @deprecated tag:v6.8.0 - Use {@link sw_example_card_headline} instead. #}.',
            });
        });

        it('suggests no rename when several new blocks could be the removed one', () => {
            const [finding] = check(
                BEFORE,
                '{% block sw_example_card %}{% block sw_example_card_a %}{% endblock %}{% block sw_example_card_b %}{% endblock %}{% endblock %}',
            );

            expect(finding.possibleRename).toBeUndefined();
        });

        it('reports a change in the flag state it happens in', () => {
            const findings = check(
                BEFORE,
                `
                    {% block sw_example_card %}
                    <template v-if="!${MAJOR}">{% block sw_example_card_title %}{% endblock %}</template>
                    {% endblock %}
                `,
            );

            expect(summary(findings)).toEqual(['Break  block_missing  sw-example-card/block:sw_example_card_title  major']);
            expect(findings[0].explanation).toBe(
                'The block is gone in the major state, so plugin template overrides of sw_example_card_title render nothing with v6.8.0.0 on.',
            );
        });

        it('allows the blocks inside a deprecated block to go away with it', () => {
            const blocks = [
                '{# @deprecated tag:v6.8.0 - Will be removed. #}',
                '{% block sw_example_card_header %}',
                '{% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}',
                '{% endblock %}',
            ].join('\n');
            const findings = check(
                `{% block sw_example_card %}${blocks}{% endblock %}`,
                `{% block sw_example_card %}<template v-if="!${MAJOR}">${blocks}</template>{% endblock %}`,
            );

            expect(summary(findings)).toEqual([
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_header  major',
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_icon  major',
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  major',
            ]);
        });

        it('keeps the removal of a block a break when only one of the blocks around its places is deprecated', () => {
            const before = [
                '{% block sw_example_card %}',
                '{# @deprecated tag:v6.7.0 - Will be removed. #}',
                '{% block sw_example_card_header %}<div>{% block sw_example_card_title %}{% endblock %}</div>{% endblock %}',
                '{% block sw_example_card_footer %}<div>{% block sw_example_card_title %}{% endblock %}</div>{% endblock %}',
                '{% endblock %}',
            ].join('\n');
            const after = before.replaceAll('{% block sw_example_card_title %}{% endblock %}', '');

            expect(summary(check(before, after))).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('allows a removal once every place of the block is deprecated, also with different versions', () => {
            const before = [
                '{% block sw_example_card %}',
                '{# @deprecated tag:v6.6.0 - Will be removed. #}',
                '{% block sw_example_card_header %}<div>{% block sw_example_card_title %}{% endblock %}</div>{% endblock %}',
                '{# @deprecated tag:v6.7.0 - Will be removed. #}',
                '{% block sw_example_card_footer %}<div>{% block sw_example_card_title %}{% endblock %}</div>{% endblock %}',
                '{% endblock %}',
            ].join('\n');
            const findings = check(before, before.replaceAll('{% block sw_example_card_title %}{% endblock %}', ''));

            expect(summary(findings)).toEqual([
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  current, major',
            ]);
            expect(findings[0].explanation).toBe(
                'The block carries @deprecated tag:v6.7.0, which announced that it goes away.',
            );
        });

        it('keeps the removal of a block a break when only a block inside it is deprecated', () => {
            const blocks = [
                '{% block sw_example_card_header %}',
                '{# @deprecated tag:v6.8.0 - Will be removed. #}',
                '{% block sw_example_card_title %}{% endblock %}',
                '{% endblock %}',
            ].join('\n');
            const findings = check(
                `{% block sw_example_card %}${blocks}{% endblock %}`,
                `{% block sw_example_card %}<template v-if="!${MAJOR}">${blocks}</template>{% endblock %}`,
            );

            expect(summary(findings)).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_header  major',
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  major',
            ]);
        });
    });

    describe('blocks that move', () => {
        const BEFORE = `
            {% block sw_example_card %}
            {% block sw_example_card_header %}{% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}{% endblock %}
            {% block sw_example_card_body %}{% endblock %}
            {% endblock %}
        `;

        it('reports a block that moved into another block, without the blocks it took along', () => {
            expect(
                summary(
                    check(
                        BEFORE,
                        `
                            {% block sw_example_card %}
                            {% block sw_example_card_header %}{% endblock %}
                            {% block sw_example_card_body %}{% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}{% endblock %}
                            {% endblock %}
                        `,
                    ),
                ),
            ).toEqual(['Break  block_moved_parent  sw-example-card/block:sw_example_card_title  current, major']);
        });

        it('reports a block that moved out of its parent', () => {
            expect(
                summary(
                    check(
                        BEFORE,
                        `
                            {% block sw_example_card %}
                            {% block sw_example_card_header %}{% endblock %}
                            {% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}
                            {% block sw_example_card_body %}{% endblock %}
                            {% endblock %}
                        `,
                    ),
                ),
            ).toEqual(['Break  block_moved_parent  sw-example-card/block:sw_example_card_title  current, major']);
        });

        it('names no override of the root when a block moves out of every block', () => {
            const findings = check(
                BEFORE,
                `
                    {% block sw_example_card %}
                    {% block sw_example_card_header %}{% endblock %}
                    {% block sw_example_card_body %}{% endblock %}
                    {% endblock %}
                    {% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}
                `,
            );

            expect(summary(findings)).toEqual([
                'Break  block_moved_parent  sw-example-card/block:sw_example_card_title  current, major',
            ]);
            expect(findings[0].explanation).toBe(
                'The block moved from sw_example_card_header to (root), so plugin overrides of sw_example_card_header ' +
                    'without {% parent %} now drop it.',
            );
            expect(findings[0].fix).toContain('{# @bc-change BlockMoved version:v6.8.0 parent:(root) #}');
        });

        it('names no override of the root when a block moves into a block', () => {
            const findings = check(
                `
                    {% block sw_example_card_body %}{% endblock %}
                    {% block sw_example_card_title %}{% endblock %}
                `,
                '{% block sw_example_card_body %}{% block sw_example_card_title %}{% endblock %}{% endblock %}',
            );

            expect(summary(findings)).toEqual([
                'Break  block_moved_parent  sw-example-card/block:sw_example_card_title  current, major',
            ]);
            expect(findings[0].explanation).toBe(
                'The block moved from (root) to sw_example_card_body, so plugin overrides of sw_example_card_body drop it.',
            );
        });

        it('reports only the new block when a block gets a new block around it', () => {
            expect(
                summary(
                    check(
                        BEFORE,
                        `
                            {% block sw_example_card %}
                            {% block sw_example_card_header %}
                            {% block sw_example_card_header_inner %}{% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}{% endblock %}
                            {% endblock %}
                            {% block sw_example_card_body %}{% endblock %}
                            {% endblock %}
                        `,
                    ),
                ),
            ).toEqual(['Addition  block_added  sw-example-card/block:sw_example_card_header_inner  current, major']);
        });

        it('reports only the removed block when the block around a block goes away', () => {
            expect(
                summary(
                    check(
                        BEFORE,
                        `
                            {% block sw_example_card %}
                            {% block sw_example_card_title %}{% block sw_example_card_icon %}{% endblock %}{% endblock %}
                            {% block sw_example_card_body %}{% endblock %}
                            {% endblock %}
                        `,
                    ),
                ),
            ).toEqual(['Break  block_missing  sw-example-card/block:sw_example_card_header  current, major']);
        });
    });

    describe('the context of a block', () => {
        it('reports a block that continued a v-if chain and no longer follows one', () => {
            expect(
                summary(
                    check(
                        '<div v-if="isLoading"></div>{% block sw_example_card_empty %}<p v-else></p>{% endblock %}',
                        '<div v-if="isLoading"></div><hr>{% block sw_example_card_empty %}<p></p>{% endblock %}',
                    ),
                ),
            ).toEqual(['Break  block_if_context_lost  sw-example-card/block:sw_example_card_empty  current, major']);
        });

        it.each([
            ['an empty block', ''],
            ['a block whose content starts no v-else', '<p></p>'],
            ['a block that starts a chain of its own', '<p v-if="isEmpty"></p>'],
        ])('reports %s that only stood behind a v-if element as information', (_case, content) => {
            const block = `{% block sw_example_card_empty %}${content}{% endblock %}`;

            expect(
                summary(check(`<div v-if="isLoading"></div>${block}`, `<div v-if="isLoading"></div><hr>${block}`)),
            ).toEqual(['Info  block_if_neighbour_lost  sw-example-card/block:sw_example_card_empty  current, major']);
        });

        it('reports another chain before a block as information', () => {
            const findings = check(
                '<div v-if="isLoading"></div>{% block sw_example_card_empty %}{% endblock %}',
                '<div v-if="hasError"></div>{% block sw_example_card_empty %}{% endblock %}',
            );

            expect(summary(findings)).toEqual([
                'Info  block_if_chain_changed  sw-example-card/block:sw_example_card_empty  current, major',
            ]);
            expect(findings[0].explanation).toContain('changed from "isLoading" to "hasError"');
        });

        it('reports a change to an earlier member of the chain too', () => {
            expect(
                summary(
                    check(
                        '<div v-if="isLoading"></div><div v-else-if="hasError"></div>{% block sw_example_card_empty %}{% endblock %}',
                        '<div v-if="isSaving"></div><div v-else-if="hasError"></div>{% block sw_example_card_empty %}{% endblock %}',
                    ),
                ),
            ).toEqual(['Info  block_if_chain_changed  sw-example-card/block:sw_example_card_empty  current, major']);
        });

        it('reports nothing when a block starts to follow a chain', () => {
            expect(
                check(
                    '<hr>{% block sw_example_card_empty %}{% endblock %}',
                    '<div v-if="isLoading"></div>{% block sw_example_card_empty %}{% endblock %}',
                ),
            ).toEqual([]);
        });

        it('reports a template variable that a block lost, per variable', () => {
            expect(
                summary(
                    check(
                        '<li v-for="(item, index) in items">{% block sw_example_card_item %}{% endblock %}</li>',
                        '<li v-for="item in items">{% block sw_example_card_item %}{% endblock %}</li>',
                    ),
                ),
            ).toEqual([
                'Break  block_scope_missing  sw-example-card/block:sw_example_card_item/scope:index  current, major',
            ]);
        });

        it('names a slot as the source of a lost slot prop', () => {
            const [finding] = check(
                '<sw-grid><template #actions="{ item }">{% block sw_example_card_item %}{% endblock %}</template></sw-grid>',
                '<sw-grid><template #actions>{% block sw_example_card_item %}{% endblock %}</template></sw-grid>',
            );

            expect(finding.explanation).toBe(
                'The template variable item from a slot is no longer available in block sw_example_card_item, so plugin overrides that use item fail at render time.',
            );
        });

        it('reports a variable that comes from somewhere else as information', () => {
            const findings = check(
                '<li v-for="item in products">{% block sw_example_card_item %}{% endblock %}</li>',
                '<li v-for="item in orders">{% block sw_example_card_item %}{% endblock %}</li>',
            );

            expect(summary(findings)).toEqual([
                'Info  block_scope_source_changed  sw-example-card/block:sw_example_card_item/scope:item  current, major',
            ]);
            expect(findings[0].explanation).toContain('now comes from "v-for orders" instead of "v-for products"');
        });

        it('reports nothing when a block gets another variable', () => {
            expect(
                check(
                    '<li v-for="item in items">{% block sw_example_card_item %}{% endblock %}</li>',
                    '<li v-for="(item, index) in items">{% block sw_example_card_item %}{% endblock %}</li>',
                ),
            ).toEqual([]);
        });
    });

    describe('crossings', () => {
        const BALANCED = `
            {% block sw_example_card %}
            {% block sw_example_card_header %}<div></div>{% endblock %}
            {% block sw_example_card_footer %}{% endblock %}
            {% endblock %}
        `;
        const CROSSING = `
            {% block sw_example_card %}
            {% block sw_example_card_header %}<div>{% endblock %}
            {% block sw_example_card_footer %}</div>{% endblock %}
            {% endblock %}
        `;

        it('reports a new element crossing with the line of the element', () => {
            const findings = check(BALANCED, CROSSING);

            expect(summary(findings)).toEqual([
                'Break  element_crosses_block  sw-example-card/crossing:div@sw_example_card_header>sw_example_card_footer  current, major',
            ]);
            expect(findings[0].at).toBe(`${CARD_FILE}:3`);
        });

        it('reports nothing for a crossing that both snapshots have', () => {
            expect(check(CROSSING, CROSSING.replace('<div>', '<div class="moved">'))).toEqual([]);
        });

        it('reports a crossing that is gone as information', () => {
            expect(summary(check(CROSSING, BALANCED))).toEqual([
                'Info  crossing_resolved  sw-example-card/crossing:div@sw_example_card_header>sw_example_card_footer  current, major',
            ]);
        });

        it('reports a v-if chain that now continues in another block', () => {
            expect(
                summary(
                    check(
                        '{% block sw_example_card_title %}<h2 v-if="title"></h2><h3 v-else></h3>{% endblock %}{% block sw_example_card_fallback %}{% endblock %}',
                        '{% block sw_example_card_title %}<h2 v-if="title"></h2>{% endblock %}{% block sw_example_card_fallback %}<h3 v-else></h3>{% endblock %}',
                    ),
                ),
            ).toEqual([
                'Break  if_chain_crosses_block  sw-example-card/crossing:v-if@sw_example_card_title>sw_example_card_fallback  current, major',
            ]);
        });
    });
});
