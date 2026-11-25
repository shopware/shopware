/**
 * @sw-package framework
 *
 * What a change counts as: announced by a marker, private, or inherited by an extend child.
 */
import { MAJOR } from '../../testing/example-components';
import { check, summary } from './sources';

describe('scripts/generate-public-api/break-check/break-check verdict', () => {
    describe('announced changes', () => {
        const BEFORE = `
            {% block sw_example_card %}
            {% block sw_example_card_header %}{% block sw_example_card_title %}{% endblock %}{% endblock %}
            {% block sw_example_card_body %}{% endblock %}
            {% endblock %}
        `;

        function markedBefore(marker: string): string {
            return BEFORE.replace('{% block sw_example_card_title %}', `${marker}{% block sw_example_card_title %}`);
        }

        function removedInMajor(marker: string): string {
            return `
                {% block sw_example_card %}
                {% block sw_example_card_header %}
                <template v-if="!${MAJOR}">
                    ${marker}
                    {% block sw_example_card_title %}{% endblock %}
                </template>
                {% endblock %}
                {% block sw_example_card_body %}{% endblock %}
                {% endblock %}
            `;
        }

        it('allows a removal in the major state that a @deprecated marker for that major announces', () => {
            const findings = check(BEFORE, removedInMajor('{# @deprecated tag:v6.8.0 - Will be removed. #}'));

            expect(summary(findings)).toEqual([
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  major',
            ]);
            expect(findings[0].explanation).toBe(
                'The block carries @deprecated tag:v6.8.0, which announced that it goes away.',
            );
        });

        it('reads the marker out of the comments directly before the block only', () => {
            const marker = '{# @deprecated tag:v6.8.0 - Will be removed. #}';

            expect(summary(check(BEFORE, removedInMajor(`{# A hint. #}\n${marker}\n{# Another hint. #}`)))).toEqual([
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  major',
            ]);
            expect(summary(check(BEFORE, removedInMajor(`${marker}<hr>`)))).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  major',
            ]);
        });

        it('keeps a removal a break when the marker names a later major', () => {
            expect(summary(check(BEFORE, removedInMajor('{# @deprecated tag:v6.9.0 - Will be removed. #}')))).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  major',
            ]);
        });

        it('allows the removal of a block whose marker names a version before the next major', () => {
            const deprecatedFor = (version: string) => markedBefore(`{# @deprecated tag:${version} - Will be removed. #}`);
            const removed = BEFORE.replace('{% block sw_example_card_title %}{% endblock %}', '');

            expect(summary(check(deprecatedFor('v6.7.0'), removed))).toEqual([
                'Allowed  deprecated_removal  sw-example-card/block:sw_example_card_title  current, major',
            ]);
            expect(summary(check(deprecatedFor('v6.8.0'), removed))).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        describe('a marker that comes with the change in the current state', () => {
            const MARKER = '{# @bc-change BlockMoved version:v6.7.0 parent:sw_example_card_body #}';
            const MOVED = `
                {% block sw_example_card %}
                {% block sw_example_card_header %}{% endblock %}
                {% block sw_example_card_body %}${MARKER}{% block sw_example_card_title %}{% endblock %}{% endblock %}
                {% endblock %}
            `;

            it('keeps the change a break', () => {
                expect(summary(check(BEFORE, MOVED))).toEqual([
                    'Break  block_moved_parent  sw-example-card/block:sw_example_card_title  current, major',
                ]);
            });

            it('allows the change once the base commit has the marker', () => {
                expect(summary(check(markedBefore(MARKER), MOVED))).toEqual([
                    'Allowed  announced_change  sw-example-card/block:sw_example_card_title  current, major',
                ]);
            });

            it('keeps a lost variable a break', () => {
                const before = '<li v-for="item in items">{% block sw_example_card_item %}{% endblock %}</li>';
                const after = `
                    <li>
                        {# @bc-change ScopeVariableRemoval version:v6.0.0 variable:item #}
                        {% block sw_example_card_item %}{% endblock %}
                    </li>
                `;

                expect(summary(check(before, after))).toEqual([
                    'Break  block_scope_missing  sw-example-card/block:sw_example_card_item/scope:item  current, major',
                ]);
            });
        });

        function movedInMajor(marker: string): string {
            return `
                {% block sw_example_card %}
                {% block sw_example_card_header %}
                <template v-if="!${MAJOR}">
                    ${marker}
                    {% block sw_example_card_title %}{% endblock %}
                </template>
                {% endblock %}
                {% block sw_example_card_body %}
                <template v-if="${MAJOR}">{% block sw_example_card_title %}{% endblock %}</template>
                {% endblock %}
                {% endblock %}
            `;
        }

        it('allows a move in the major state that a @bc-change marker with the new parent announces', () => {
            const findings = check(
                BEFORE,
                movedInMajor('{# @bc-change BlockMoved version:v6.8.0 parent:sw_example_card_body #}'),
            );

            expect(summary(findings)).toEqual([
                'Allowed  announced_change  sw-example-card/block:sw_example_card_title  major',
            ]);
            expect(findings[0].explanation).toBe(
                'The block carries @bc-change BlockMoved version:v6.8.0, which announced this change.',
            );
        });

        it('keeps a move in the major state a break when its marker names a released version', () => {
            expect(
                summary(
                    check(BEFORE, movedInMajor('{# @bc-change BlockMoved version:v6.7.0 parent:sw_example_card_body #}')),
                ),
            ).toEqual(['Break  block_moved_parent  sw-example-card/block:sw_example_card_title  major']);
        });

        it('allows a move to the root in the major state that a @bc-change marker with parent (root) announces', () => {
            const movedToRoot = `
                {% block sw_example_card %}
                {% block sw_example_card_header %}
                <template v-if="!${MAJOR}">
                    {# @bc-change BlockMoved version:v6.8.0 parent:(root) #}
                    {% block sw_example_card_title %}{% endblock %}
                </template>
                {% endblock %}
                {% block sw_example_card_body %}{% endblock %}
                {% endblock %}
                <template v-if="${MAJOR}">{% block sw_example_card_title %}{% endblock %}</template>
            `;

            expect(summary(check(BEFORE, movedToRoot))).toEqual([
                'Allowed  announced_change  sw-example-card/block:sw_example_card_title  major',
            ]);
        });

        it('keeps a move a break when the marker names another parent', () => {
            expect(
                summary(check(BEFORE, movedInMajor('{# @bc-change BlockMoved version:v6.8.0 parent:sw_example_card #}'))),
            ).toEqual(['Break  block_moved_parent  sw-example-card/block:sw_example_card_title  major']);
        });

        it('allows a lost variable that a @bc-change marker announces', () => {
            const before = '<li v-for="(item, index) in items">{% block sw_example_card_item %}{% endblock %}</li>';
            const after = `
                <template v-if="!${MAJOR}">
                    <li v-for="(item, index) in items">
                        {# @bc-change ScopeVariableRemoval version:v6.8.0 variable:index #}
                        {% block sw_example_card_item %}{% endblock %}
                    </li>
                </template>
                <template v-else>
                    <li v-for="item in items">{% block sw_example_card_item %}{% endblock %}</li>
                </template>
            `;

            expect(summary(check(before, after))).toEqual([
                'Allowed  announced_change  sw-example-card/block:sw_example_card_item/scope:index  major',
            ]);
        });
    });

    describe('private components and extend children', () => {
        const CARD = '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}';
        const WITHOUT_TITLE = '{% block sw_example_card %}{% endblock %}';

        it('reports every change to a private component as private', () => {
            expect(summary(check({ card: CARD, privateCard: true }, { card: WITHOUT_TITLE, privateCard: true }))).toEqual([
                'Private  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('keeps the breaks of a component that becomes private with the change', () => {
            expect(summary(check({ card: CARD }, { card: WITHOUT_TITLE, privateCard: true }))).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('reports the changes to a component that becomes public as private', () => {
            expect(summary(check({ card: CARD, privateCard: true }, { card: WITHOUT_TITLE }))).toEqual([
                'Private  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('reports a change that an extend child inherits once, on the parent', () => {
            const findings = check({ card: CARD, tile: '' }, { card: WITHOUT_TITLE, tile: '' });

            expect(summary(findings)).toEqual([
                'Break  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
            expect(findings[0].alsoAffects).toEqual(['sw-example-tile']);
        });

        it('keeps the break of a public child of a private parent', () => {
            expect(
                summary(
                    check({ card: CARD, tile: '', privateCard: true }, { card: WITHOUT_TITLE, tile: '', privateCard: true }),
                ),
            ).toEqual([
                'Break  block_missing  sw-example-tile/block:sw_example_card_title  current, major',
                'Private  block_missing  sw-example-card/block:sw_example_card_title  current, major',
            ]);
        });

        it('reports a change that only the child makes on the child', () => {
            const before =
                '{% block sw_example_card_title %}{% parent %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}';

            expect(
                summary(
                    check(
                        { card: CARD, tile: before },
                        { card: CARD, tile: '{% block sw_example_card_title %}{% endblock %}' },
                    ),
                ),
            ).toEqual(['Break  block_missing  sw-example-tile/block:sw_example_tile_badge  current, major']);
        });
    });

    it('orders the findings by group, rule and element', () => {
        expect(
            summary(
                check(
                    '{% block sw_example_card_b %}{% endblock %}{% block sw_example_card_a %}{% endblock %}<li v-for="item in items">{% block sw_example_card_item %}{% endblock %}</li>',
                    '{% block sw_example_card_new %}{% endblock %}<li>{% block sw_example_card_item %}{% endblock %}</li>',
                ),
            ),
        ).toEqual([
            'Break  block_missing  sw-example-card/block:sw_example_card_a  current, major',
            'Break  block_missing  sw-example-card/block:sw_example_card_b  current, major',
            'Break  block_scope_missing  sw-example-card/block:sw_example_card_item/scope:item  current, major',
            'Addition  block_added  sw-example-card/block:sw_example_card_new  current, major',
        ]);
    });
});
