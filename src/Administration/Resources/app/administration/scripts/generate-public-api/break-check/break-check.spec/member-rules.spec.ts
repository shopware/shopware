/**
 * @sw-package framework
 *
 * What the break check reports for a change to the members of a component. The fixtures in `testing/__fixtures__/`
 * hold the complete texts of the findings, these tests the cases around each rule. `method-rules.spec.ts` covers
 * the signatures of methods.
 */
import { MAJOR } from '../../testing/example-components';
import type { ExampleSources } from '../../testing/example-components';
import { check, memberSummary, methods, template } from './sources';

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

        it('reports a removed slot and a removed ref as a break, and nothing for a removed CSS class', () => {
            const before = template('<div ref="root" class="card"><slot name="footer"></slot></div>');

            expect(memberSummary(check(before, template('<div></div>')))).toEqual([
                'Break  ref_missing  ref:root',
                'Break  slot_missing  slot:footer',
            ]);
        });

        it('reports a new slot as an addition, and nothing for a new ref', () => {
            const after = template('<div ref="root"><slot name="footer"></slot></div>');

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
            expect(check(template('<i></i><b ref="label"></b>'), template('<p><b ref="label"></b></p><i></i>'))).toEqual([]);
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

        it('allows a slot and a ref to go away in the major state with a deprecated block around them', () => {
            const block = (inner: string) =>
                `{# @deprecated tag:v6.8.0 - Will be removed. #}\n{% block sw_example_card_title %}${inner}{% endblock %}`;
            const content = '<div><h3 ref="title"><slot name="title"></slot></h3></div>';
            const before = { template: `{% block sw_example_card %}${block(content)}{% endblock %}` };
            const after = {
                template: `{% block sw_example_card %}<template v-if="!${MAJOR}">${block(content)}</template>{% endblock %}`,
            };

            expect(memberSummary(check(before, after))).toEqual([
                'Allowed  deprecated_removal  block:sw_example_card_title',
                'Allowed  deprecated_removal  ref:title',
                'Allowed  deprecated_removal  slot:title',
            ]);
        });

        it('keeps the removal of a slot a break when only one of its places is in a deprecated block', () => {
            const blocks = [
                '{# @deprecated tag:v6.8.0 - Will be removed. #}',
                '{% block sw_example_card_title %}<slot name="title"></slot>{% endblock %}',
                '{% block sw_example_card_footer %}<slot name="title"></slot>{% endblock %}',
            ].join('\n');
            const before = { template: `{% block sw_example_card %}${blocks}{% endblock %}` };
            const after = {
                template: `{% block sw_example_card %}<template v-if="!${MAJOR}">${blocks}</template>{% endblock %}`,
            };

            expect(memberSummary(check(before, after))).toEqual([
                'Break  block_missing  block:sw_example_card_footer',
                'Break  slot_missing  slot:title',
                'Allowed  deprecated_removal  block:sw_example_card_title',
            ]);
        });

        it('keeps the removal of a slot a break when only one parent of its block is deprecated', () => {
            const before = {
                template: [
                    '{% block sw_example_card %}',
                    '{# @deprecated tag:v6.7.0 - Will be removed. #}',
                    '{% block sw_example_card_header %}<div>{% block sw_example_card_title %}<slot name="title"></slot>{% endblock %}</div>{% endblock %}',
                    '{% block sw_example_card_footer %}<div>{% block sw_example_card_title %}<slot name="title"></slot>{% endblock %}</div>{% endblock %}',
                    '{% endblock %}',
                ].join('\n'),
            };
            const after = { template: before.template.replaceAll('<slot name="title"></slot>', '') };

            expect(memberSummary(check(before, after))).toEqual(['Break  slot_missing  slot:title']);
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

        it('reports another declared type as a break, and a first or a dropped type as widened', () => {
            const typed = (type: string) => ({ options: `props: { size: ${type} }` });
            const untyped = { options: "props: ['size']" };

            expect(memberSummary(check(typed('String'), typed('Number')))).toEqual(['Break  type_changed  prop:size']);
            expect(memberSummary(check(untyped, typed('Number')))).toEqual(['Info  type_widened  prop:size']);
            expect(memberSummary(check(typed('Number'), untyped))).toEqual(['Info  type_widened  prop:size']);
        });

        it('reports nothing when only the order of the prop constructors changes', () => {
            const typed = (type: string) => ({ options: `props: { size: ${type} }` });

            expect(check(typed('[String, Number]'), typed('[Number, String]'))).toEqual([]);
        });

        it('reports another type of a computed property as a break', () => {
            const typed = (type: string) => ({
                options: `computed: { total(): ${type} { return 1; } }`,
                extension: 'ts' as const,
            });

            expect(memberSummary(check(typed('number'), typed('string')))).toEqual([
                'Break  return_type_changed  computed:total',
            ]);
        });
    });

    describe('extend children', () => {
        const tile = (tileOptions = ''): ExampleSources => ({
            card: '{% block sw_example_card %}{% endblock %}',
            tile: '',
            cardOptions: 'methods: { onSave() {} }, data() { return { pagination: { page: 1, limit: 25 } }; }',
            tileOptions,
        });

        it('reports nothing when a child stops overriding a member it inherits', () => {
            const overriding = tile("methods: { onSave() { return this.$super('onSave'); } }");

            expect(check(overriding, tile())).toEqual([]);
        });

        it('reports a member that the parent loses once, on the parent, with the child it reaches', () => {
            const before = tile();
            const after = { ...tile(), cardOptions: 'data() { return { pagination: { page: 1, limit: 25 } }; }' };

            expect(check(before, after)).toMatchObject([
                {
                    rule: 'method_missing',
                    element: 'component:sw-example-card/method:onSave',
                    alsoAffects: ['sw-example-tile'],
                },
            ]);
        });

        it('reports nothing when a child drops an override that only returns $super of an async parent method', () => {
            const asyncParent = (tileOptions = ''): ExampleSources => ({
                ...tile(tileOptions),
                cardOptions: 'methods: { async onSave() {} }',
            });
            const passthrough = asyncParent("methods: { onSave() { return this.$super('onSave'); } }");

            expect(check(passthrough, asyncParent())).toEqual([]);
            expect(check(asyncParent(), passthrough)).toEqual([]);
        });

        it('reports an async override that only returns $super of a sync parent method', () => {
            const asyncOverride = {
                ...tile("methods: { async onSave() { return this.$super('onSave'); } }"),
                cardOptions: 'methods: { onSave() {} }',
            };

            expect(memberSummary(check({ ...asyncOverride, tileOptions: '' }, asyncOverride))).toEqual([
                'Break  method_signature_changed  component:sw-example-tile/method:onSave',
            ]);
        });

        it('lets a data key of the child replace the whole object it inherits', () => {
            const after = tile('data() { return { pagination: { page: 1 } }; }');

            expect(memberSummary(check(tile(), after))).toEqual([
                'Break  data_missing  component:sw-example-tile/data:pagination.limit',
            ]);
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
