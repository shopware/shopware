/**
 * @sw-package framework
 *
 * What the break check reports for a change to the members of a component. The fixtures in `testing/__fixtures__/`
 * hold the complete texts of the findings, these tests the cases around each rule.
 */
import { MAJOR } from '../../testing/example-components';
import type { Card } from '../../testing/example-components';
import { check, memberSummary } from './sources';

/** One method per line, as the sources write them: a comment belongs to the member in the line below it. */
function methods(...declarations: string[]): Card {
    return { options: `methods: {\n${declarations.join(',\n')}\n}` };
}

function template(markup: string): Card {
    return { template: `{% block sw_example_card %}${markup}{% endblock %}` };
}

describe('scripts/generate-public-api/break-check/break-check member rules', () => {
    describe('members that come and go', () => {
        it.each([
            ['mixin', "mixins: ['listing']"],
            ['inject', "inject: ['acl']"],
            ['provide', 'provide() { return { acl: 1 }; }'],
            ['prop', "props: ['acl']"],
            ['emit', "emits: ['acl']"],
            ['data', 'data() { return { acl: 1 }; }'],
            ['computed', 'computed: { acl() {} }'],
            ['method', 'methods: { acl() {} }'],
        ])('reports a removed %s as a break and a new one as an addition', (kind, options) => {
            const name = kind === 'mixin' ? 'listing' : 'acl';

            expect(memberSummary(check({ options }, {}))).toEqual([`Break  ${kind}_missing  ${kind}:${name}`]);
            expect(memberSummary(check({}, { options }))).toEqual([`Addition  ${kind}_added  ${kind}:${name}`]);
        });

        it('reports a removed slot and a removed ref as a break, and a removed class as a hint', () => {
            const before = template('<div ref="root" class="card"><slot name="footer"></slot></div>');

            expect(memberSummary(check(before, template('<div></div>')))).toEqual([
                'Break  ref_missing  ref:root',
                'Break  slot_missing  slot:footer',
                'Info  class_missing  class:card',
            ]);
        });

        it('reports a new slot as an addition, and nothing for a new ref or class', () => {
            const after = template('<div ref="root" class="card"><slot name="footer"></slot></div>');

            expect(memberSummary(check(template('<div></div>'), after))).toEqual(['Addition  slot_added  slot:footer']);
        });

        it('reports a nested data key by its path', () => {
            const before = { options: 'data() { return { pagination: { page: 1, limit: 25 } }; }' };
            const after = { options: 'data() { return { pagination: { page: 1 } }; }' };

            expect(memberSummary(check(before, after))).toEqual(['Break  data_missing  data:pagination.limit']);
        });

        it('points a removed member to the option that held it and names a member that looks like its new name', () => {
            const [removed] = check(
                { options: 'props: {\n placeholder: String,\n size: Number },' },
                { options: 'props: {\n placeholderText: String,\n size: Number },' },
            );

            expect(removed).toMatchObject({
                rule: 'prop_missing',
                possibleRename: 'placeholderText',
                at: 'src/module/sw-example/component/sw-example-card/index.js:3',
            });
        });

        it('points a removed member of the template to the closest block that is left', () => {
            const before = template('\n{% block sw_example_card_body %}\n<div ref="body"></div>{% endblock %}');
            const removed = check(before, template('')).find(({ rule }) => rule === 'ref_missing');

            expect(removed).toMatchObject({
                rule: 'ref_missing',
                at: 'src/module/sw-example/component/sw-example-card/sw-example-card.html.twig:1',
            });
        });

        it('reports a member that only the major state loses in that state', () => {
            const before = template('<div ref="root"></div>');
            const after = template(`<div v-if="!${MAJOR}" ref="root"></div>`);

            expect(check(before, after)).toMatchObject([
                { rule: 'ref_missing', element: 'component:sw-example-card/ref:root', states: ['major'] },
            ]);
        });

        it('reports nothing when a member only moves inside the source or the template', () => {
            expect(check(methods('a() {}', 'b() {}'), methods('b() {}', 'a() {}'))).toEqual([]);
            expect(
                check(
                    template('<i class="icon"></i><b ref="label" class="label"></b>'),
                    template('<p><b ref="label" class="label"></b></p><i class="icon"></i>'),
                ),
            ).toEqual([]);
        });
    });

    describe('announced and private members', () => {
        it('allows the removal of a member whose @deprecated version is released', () => {
            const before = methods('/** @deprecated tag:v6.7.0 - Will be removed. */\nload() {}');

            expect(memberSummary(check(before, methods()))).toEqual(['Allowed  deprecated_removal  method:load']);
        });

        it('keeps the removal a break while the announced version is not released', () => {
            const before = methods('/** @deprecated tag:v6.8.0 - Will be removed. */\nload() {}');

            expect(memberSummary(check(before, methods()))).toEqual(['Break  method_missing  method:load']);
        });

        it('reports a change to a @private member of a public component as private', () => {
            const before = methods('/** @private */\nload(id) {}', '/** @private */\nreset() {}');
            const after = methods('/** @private */\nload() {}');

            expect(memberSummary(check(before, after))).toEqual([
                'Private  method_missing  method:reset',
                'Private  param_missing  method:load/param:id',
            ]);
        });

        it('keeps a change a break when the member becomes @private with it', () => {
            const before = methods('load(id) {}');
            const after = methods('/** @private */\nload() {}');

            expect(memberSummary(check(before, after))).toEqual(['Break  param_missing  method:load/param:id']);
        });

        it('reports a new marker on a member as a hint', () => {
            const after = methods(
                '/** @deprecated tag:v6.8.0 - Will be removed. */\nload() {}',
                '/** @bc-change ParameterRemoval version:v6.8.0 parameter:id */\nopen(id) {}',
            );

            expect(memberSummary(check(methods('load() {}', 'open(id) {}'), after))).toEqual([
                'Info  bc_change_added  method:open/param:id',
                'Info  deprecation_added  method:load',
            ]);
        });
    });

    describe('props', () => {
        it('reports a prop that became required, and nothing for one that became optional', () => {
            const optional = { options: 'props: { title: { type: String, required: false } }' };
            const required = { options: 'props: { title: { type: String, required: true } }' };

            expect(memberSummary(check(optional, required))).toEqual(['Break  prop_now_required  prop:title']);
            expect(check(required, optional)).toEqual([]);
        });

        it('allows a prop to become required once its @bc-change version is released', () => {
            const before = {
                options: 'props: {\n/** @bc-change PropBecomesRequired version:v6.7.0 */\ntitle: { type: String } }',
            };
            const after = { options: 'props: { title: { type: String, required: true } }' };

            expect(memberSummary(check(before, after))).toEqual(['Allowed  announced_change  prop:title']);
        });

        it('reports another declared type as a hint, and a first type as widened', () => {
            const typed = (type: string) => ({ options: `props: { size: ${type} }` });

            expect(memberSummary(check(typed('String'), typed('Number')))).toEqual(['Info  type_changed  prop:size']);
            expect(memberSummary(check({ options: "props: ['size']" }, typed('Number')))).toEqual([
                'Info  type_widened  prop:size',
            ]);
        });
    });

    describe('method signatures', () => {
        it('reports a removed parameter, also the last one', () => {
            expect(memberSummary(check(methods('load(id, force) {}'), methods('load() {}')))).toEqual([
                'Break  param_missing  method:load/param:force',
                'Break  param_missing  method:load/param:id',
            ]);
        });

        it('allows the removal of a parameter that a released @bc-change marker names', () => {
            const before = methods('/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force) {}');

            expect(memberSummary(check(before, methods('load() {}')))).toEqual([
                'Break  param_missing  method:load/param:id',
                'Allowed  announced_change  method:load/param:force',
            ]);
        });

        it('reports a new parameter as a break when it is required and as an addition when it is optional', () => {
            expect(memberSummary(check(methods('load(id) {}'), methods('load(id, force, limit = 25, ...rest) {}')))).toEqual(
                [
                    'Break  param_now_required  method:load/param:force',
                    'Addition  param_added  method:load/param:limit',
                    'Addition  param_added  method:load/param:rest',
                ],
            );
        });

        it('reports a parameter that lost its default', () => {
            expect(memberSummary(check(methods('load(id = null) {}'), methods('load(id) {}')))).toEqual([
                'Break  param_now_required  method:load/param:id',
            ]);
        });

        it('reports a renamed parameter as a hint and reordered parameters as a break', () => {
            expect(memberSummary(check(methods('load(id, force) {}'), methods('load(key, force) {}')))).toEqual([
                'Info  method_param_renamed  method:load/param:key',
            ]);
            expect(memberSummary(check(methods('load(id, force) {}'), methods('load(force, id) {}')))).toEqual([
                'Break  method_signature_changed  method:load',
            ]);
        });

        it('reports a method that switched between sync and async', () => {
            expect(memberSummary(check(methods('load() {}'), methods('async load() {}')))).toEqual([
                'Break  method_signature_changed  method:load',
            ]);
            expect(memberSummary(check(methods('async load() {}'), methods('load() {}')))).toEqual([
                'Break  method_signature_changed  method:load',
            ]);
        });

        it('compares no parameters of a method that is no function in the source', () => {
            expect(check(methods('load(id) {}'), methods('load: loadById'))).toEqual([]);
        });

        it('reports nothing when only the code inside a method changes', () => {
            expect(check(methods('load(id) { return id; }'), methods('load(id) { return this.find(id) ?? null; }'))).toEqual(
                [],
            );
        });
    });

    describe('template refs', () => {
        it('reports a ref that points to another tag or became a list', () => {
            expect(
                memberSummary(check(template('<sw-data-grid ref="grid" />'), template('<sw-entity-listing ref="grid" />'))),
            ).toEqual([
                'Break  ref_target_changed  ref:grid',
            ]);
            expect(
                memberSummary(check(template('<input ref="field">'), template('<p v-for="i in 2"><input ref="field"></p>'))),
            ).toEqual([
                'Break  ref_target_changed  ref:field',
            ]);
        });

        it('reports nothing for a ref that keeps its tags in another order', () => {
            const before = template('<a v-if="link" ref="target"></a><b v-else ref="target"></b>');
            const after = template('<b v-if="!link" ref="target"></b><a v-else ref="target"></a>');

            expect(check(before, after)).toEqual([]);
        });
    });
});
