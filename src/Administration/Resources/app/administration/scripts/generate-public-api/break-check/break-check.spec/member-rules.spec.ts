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

        it('reports a removed or new data object once, without its keys', () => {
            const before = { options: 'data() { return { pagination: { page: 1, limit: 25 } }; }' };
            const after = { options: 'data() { return {}; }' };

            expect(memberSummary(check(before, after))).toEqual(['Break  data_missing  data:pagination']);
            expect(memberSummary(check(after, before))).toEqual(['Addition  data_added  data:pagination']);
        });

        it('reports every removed key with a dot in its name, which only data nests', () => {
            const before = { options: "inject: { sw: 'sw', 'sw.acl': 'sw.acl' }" };

            expect(memberSummary(check(before, {}))).toEqual([
                'Break  inject_missing  inject:sw',
                'Break  inject_missing  inject:sw.acl',
            ]);
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

            expect(memberSummary(check(before, after))).toEqual([
                'Break  param_missing  method:load/param:id',
                'Break  visibility_became_private  method:load',
            ]);
        });

        it('reports a member that becomes @private as a break, until its @bc-change version is released', () => {
            const privateAfter = {
                options:
                    'props: {\n/** @private */\nsize: String },\ndata() { return {\n/** @private */\npagination: { page: 1 } }; }',
            };
            const publicBefore = { options: 'props: { size: String },\ndata() { return { pagination: { page: 1 } }; }' };
            const announced = methods('/** @bc-change BecomesPrivate version:v6.7.0 */\nonSave() {}');

            expect(memberSummary(check(publicBefore, privateAfter))).toEqual([
                'Break  visibility_became_private  data:pagination',
                'Break  visibility_became_private  prop:size',
            ]);
            expect(memberSummary(check(announced, methods('/** @private */\nonSave() {}')))).toEqual([
                'Allowed  announced_change  method:onSave',
            ]);
        });

        it('lets the keys of a data object follow its @deprecated or @private marker', () => {
            const marked = (marker: string, keys: string) => ({
                options: `data() { return {\n/** ${marker} */\npagination: { ${keys} } }; }`,
            });
            const deprecated = '@deprecated tag:v6.7.0 - Will be removed.';
            const removed = { options: 'data() { return {}; }' };

            expect(memberSummary(check(marked(deprecated, 'page: 1, limit: 25'), removed))).toEqual([
                'Allowed  deprecated_removal  data:pagination',
            ]);
            expect(memberSummary(check(marked('@private', 'page: 1, limit: 25'), removed))).toEqual([
                'Private  data_missing  data:pagination',
            ]);
            expect(memberSummary(check(marked(deprecated, 'page: 1, limit: 25'), marked(deprecated, 'page: 1')))).toEqual([
                'Allowed  deprecated_removal  data:pagination.limit',
            ]);
            expect(memberSummary(check(marked('@private', 'page: 1, limit: 25'), marked('@private', 'page: 1')))).toEqual([
                'Private  data_missing  data:pagination.limit',
            ]);
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

        it('keeps the removal of a ref a break when only one of its places is in a deprecated block', () => {
            const deprecated =
                '{# @deprecated tag:v6.7.0 - Will be removed. #}\n{% block sw_example_card_a %}<div ref="grid"></div>{% endblock %}';
            const plain = '{% block sw_example_card_b %}<div ref="grid"></div>{% endblock %}';
            const after = template('{% block sw_example_card_b %}<div></div>{% endblock %}');

            [`${deprecated}\n${plain}`, `${plain}\n${deprecated}`].forEach((blocks) => {
                expect(memberSummary(check(template(blocks), after))).toEqual([
                    'Break  ref_missing  ref:grid',
                    'Allowed  deprecated_removal  block:sw_example_card_a',
                ]);
            });
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

        it('reports a changed or removed default value of a prop, and nothing for a new default or another factory', () => {
            const sized = (option: string) => ({ options: `props: { size: { type: String${option} } }` });

            expect(check(sized(", default: 'medium'"), sized(", default: 'large'"))).toMatchObject([
                {
                    rule: 'prop_default_changed',
                    group: 'Break',
                    explanation:
                        "The default of the prop changed from 'medium' to 'large', so plugin templates that render sw-example-card without size get another value.",
                },
            ]);
            expect(check(sized(", default: 'medium'"), sized(''))).toMatchObject([
                {
                    rule: 'prop_default_changed',
                    group: 'Break',
                    explanation:
                        'The prop no longer has a default, so plugin templates that render sw-example-card without size leave it undefined.',
                },
            ]);
            expect(memberSummary(check(sized(', default: () => []'), sized('')))).toEqual([
                'Break  prop_default_changed  prop:size',
            ]);
            expect(check(sized(''), sized(", default: 'medium'"))).toEqual([]);
            expect(check(sized(', default: () => []'), sized(', default: () => [1]'))).toEqual([]);
        });

        it('allows a changed default of a prop once a @bc-change marker with the new value is released', () => {
            const marked = (value: string) => ({
                options: `props: {\n/** @bc-change PropDefaultValueChange version:v6.7.0 default:${value} */\nsize: { type: String, default: 'medium' } }`,
            });
            const large = { options: "props: { size: { type: String, default: 'large' } }" };

            expect(memberSummary(check(marked("'large'"), large))).toEqual(['Allowed  announced_change  prop:size']);
            expect(memberSummary(check(marked("'small'"), large))).toEqual(['Break  prop_default_changed  prop:size']);
            expect(memberSummary(check(marked('undefined'), { options: 'props: { size: String }' }))).toEqual([
                'Allowed  announced_change  prop:size',
            ]);
        });

        it('reports a PropType on the same constructor as widened, and a PropType on another constructor as a break', () => {
            const typed = (type: string) => ({
                script: `import template from './sw-example-card.html.twig';
                    export default Shopware.Component.wrapComponentConfig({ template, props: { media: { type: ${type} } } });`,
                extension: 'ts' as const,
            });

            expect(check(typed('Object'), typed('Object as PropType<Media | null>'))).toMatchObject([
                {
                    rule: 'type_widened',
                    group: 'Info',
                    explanation:
                        'The prop now declares the type Media | null. Vue still checks a value against object, as before.',
                },
            ]);
            expect(memberSummary(check(typed('Array'), typed('Array as PropType<Media[]>')))).toEqual([
                'Info  type_widened  prop:media',
            ]);
            expect(memberSummary(check(typed('Object'), typed('Array as PropType<Media[]>')))).toEqual([
                'Break  type_changed  prop:media',
            ]);
            expect(memberSummary(check(typed('Object as PropType<Media>'), typed('Object as PropType<Product>')))).toEqual([
                'Break  type_changed  prop:media',
            ]);
            expect(
                memberSummary(check(typed('String as PropType<string>'), typed("String as PropType<'a' | 'b'>"))),
            ).toEqual(['Break  type_changed  prop:media']);
        });

        it('compares a constructor outside the built-in ones by its name', () => {
            const typed = (type: string) => ({ options: `props: { product: ${type} }` });
            const explanations = (before: string, after: string) =>
                check(typed(before), typed(after)).map(({ rule, explanation }) => `${rule}: ${explanation}`);

            expect(explanations('Object', 'Entity')).toEqual([
                'type_changed: The declared type of the prop changed from object to Entity, so values that plugin templates pass as product may no longer fit.',
            ]);
            expect(explanations('Entity', 'Criteria')).toEqual([
                'type_changed: The declared type of the prop changed from Entity to Criteria, so values that plugin templates pass as product may no longer fit.',
            ]);
            expect(explanations('[String, null]', '[String, Criteria]')).toEqual([
                'type_changed: The declared type of the prop changed from null | string to Criteria | string, so values that plugin templates pass as product may no longer fit.',
            ]);
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
