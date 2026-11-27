/**
 * @sw-package framework
 *
 * What the break check says about a finding beyond the summary: a headline, the line at the base commit, the
 * comment that the fix adds and plugin code that the change breaks.
 */
import { buildModel } from '../../read/build-model';
import type { SourceTree } from '../../source/source-tree';
import { CARD_FILE, MAJOR, OPTIONS, treeOf } from '../../testing/example-components';
import { classTree, MAIN, SERVICE_FILE } from '../../testing/example-services';
import { optionsTree } from '../../testing/example-stores';
import { explainChanges } from '../break-check';
import { summaryOf } from '../finding';
import type { ExplainedFinding, FindingDetail } from '../finding';
import { check, explain, methods, template } from './sources';

const SCRIPT_FILE = 'src/module/sw-example/component/sw-example-card/index.js';

function explainTrees(before: SourceTree, after: SourceTree): ExplainedFinding[] {
    return explainChanges(buildModel(before, OPTIONS), buildModel(after, OPTIONS));
}

/** The detail of the one finding of `rule`. */
function detailOf(findings: ExplainedFinding[], rule: string): FindingDetail {
    const [finding, ...others] = findings.filter((candidate) => candidate.rule === rule);

    expect(others).toEqual([]);

    return finding.detail;
}

const WITH_INDEX = `{% block sw_example_card %}
<li v-for="(item, index) in items">
{% block sw_example_card_item %}{% endblock %}
</li>
{% endblock %}`;

const EVERY_MEMBER = {
    options: [
        "mixins: [Mixin.getByName('listing')]",
        "inject: ['acl']",
        'provide() { return { card: this }; }',
        'props: { title: String }',
        "emits: ['change']",
        'data() { return { page: 1 }; }',
        'computed: { total() { return 1; } }',
    ].join(',\n'),
    template: '{% block sw_example_card %}<div ref="root"><slot name="footer"></slot></div>{% endblock %}',
};

const NO_MEMBER = { options: '', template: '{% block sw_example_card %}<div></div>{% endblock %}' };

describe('scripts/generate-public-api/break-check/break-check finding details', () => {
    it('leaves the summary of every finding as it is', () => {
        const before = methods('load(id, force) {}', 'reset() {}');
        const after = methods('load(id) {}');

        expect(explain(before, after).map(summaryOf)).toEqual(check(before, after));
    });

    it('names the changed element, its line at the base commit, the comment of the fix and plugin code that breaks', () => {
        const findings = explain(WITH_INDEX, WITH_INDEX.replace('(item, index)', 'item'));

        expect(detailOf(findings, 'block_scope_missing')).toEqual({
            headline: 'The template variable index is no longer available',
            atBase: { file: CARD_FILE, line: 3 },
            marker: '{# @bc-change ScopeVariableRemoval version:v6.8.0 variable:index #}',
            example: {
                language: 'twig',
                lines: [
                    '{% block sw_example_card_item %}',
                    '    <my-plugin-content :value="index" />',
                    '{% endblock %}',
                ],
                broken: 1,
            },
        });
    });

    it('gives the line an element had at the base commit and says that the head has none for it', () => {
        const [finding] = explain(methods('load(id) {}', 'reset() {}'), methods('reset() {}'));

        expect(finding).toMatchObject({
            rule: 'method_missing',
            at: `${SCRIPT_FILE}:3`,
            detail: { headline: 'The method load is gone', atBase: { file: SCRIPT_FILE, line: 4 }, isGone: true },
        });
    });

    it('says in the headline when an element is only gone in the major state', () => {
        const findings = explain(
            '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}',
            `{% block sw_example_card %}<template v-if="!${MAJOR}">{% block sw_example_card_title %}{% endblock %}</template>{% endblock %}`,
        );

        expect(detailOf(findings, 'block_missing').headline).toBe(
            'The block sw_example_card_title is gone in the major state',
        );
    });

    describe('the comment that the fix adds', () => {
        it('is a Twig comment for a block and names the block that took its place', () => {
            const findings = explain(
                '{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}',
                '{% block sw_example_card %}{% block sw_example_card_headline %}{% endblock %}{% endblock %}',
            );

            expect(detailOf(findings, 'block_missing').marker).toBe(
                '{# @deprecated tag:v6.8.0 - Use {@link sw_example_card_headline} instead. #}',
            );
        });

        it('is a doc comment for a member of a script, with the keys of the change', () => {
            const findings = explain(methods('load(id, force) {}'), methods('load(id) {}'));

            expect(detailOf(findings, 'param_missing').marker).toBe(
                '/** @bc-change ParameterRemoval version:v6.8.0 parameter:force */',
            );
        });

        it('is missing for a break that no marker announces', () => {
            const findings = explain(methods('load() { return 1; }'), methods('async load() { return 1; }'));

            expect(detailOf(findings, 'method_signature_changed')).toMatchObject({
                headline: 'The method load now returns a Promise',
            });
            expect(detailOf(findings, 'method_signature_changed').marker).toBeUndefined();
        });
    });

    describe('plugin code that a break of a template breaks', () => {
        it('overrides a block that is gone', () => {
            const findings = explain(template('{% block sw_example_card_title %}{% endblock %}'), template(''));

            expect(detailOf(findings, 'block_missing').example).toEqual({
                language: 'twig',
                lines: ['{% block sw_example_card_title %}', '    <my-plugin-content />', '{% endblock %}'],
                broken: 0,
            });
        });

        it('overrides the block that a moved block left, because an override of the moved block still works', () => {
            const findings = explain(
                template(
                    '{% block sw_example_card_header %}{% block sw_example_card_title %}{% endblock %}{% endblock %}{% block sw_example_card_body %}{% endblock %}',
                ),
                template(
                    '{% block sw_example_card_header %}{% endblock %}{% block sw_example_card_body %}{% block sw_example_card_title %}{% endblock %}{% endblock %}',
                ),
            );

            expect(detailOf(findings, 'block_moved_parent')).toMatchObject({
                headline: 'The block sw_example_card_title moved to sw_example_card_body',
                marker: '{# @bc-change BlockMoved version:v6.8.0 parent:sw_example_card_body #}',
                example: { lines: ['{% block sw_example_card_header %}', '    <my-plugin-content />', '{% endblock %}'] },
            });
        });

        it('continues the v-if chain that a block no longer follows', () => {
            const findings = explain(
                template('<div v-if="isLoading"></div>{% block sw_example_card_empty %}<p v-else></p>{% endblock %}'),
                template('<div v-if="isLoading"></div><hr>{% block sw_example_card_empty %}<p></p>{% endblock %}'),
            );

            expect(detailOf(findings, 'block_if_context_lost')).toMatchObject({
                headline: 'The block sw_example_card_empty no longer follows a v-if',
                example: {
                    lines: ['{% block sw_example_card_empty %}', '    <my-plugin-content v-else />', '{% endblock %}'],
                    broken: 1,
                },
            });
        });

        it('overrides the block in which an element opens that closes in another block', () => {
            const findings = explain(
                template(
                    '{% block sw_example_card_header %}<div></div>{% endblock %}{% block sw_example_card_footer %}{% endblock %}',
                ),
                template(
                    '{% block sw_example_card_header %}<div>{% endblock %}{% block sw_example_card_footer %}</div>{% endblock %}',
                ),
            );

            expect(detailOf(findings, 'element_crosses_block')).toMatchObject({
                headline: 'A <div> element opens in block sw_example_card_header and closes in block sw_example_card_footer',
                example: { lines: ['{% block sw_example_card_header %}', '    <my-plugin-content />', '{% endblock %}'] },
            });
        });
    });

    describe('plugin code that a break of a member of a component breaks', () => {
        it('overrides a method and passes on the parameters it had at the base commit', () => {
            const findings = explain(methods('load(id, ...rest) {}', 'reset() {}'), methods('load(id) {}'));
            const override = (member: string[]) => [
                "Shopware.Component.override('sw-example-card', {",
                '    methods: {',
                ...member,
                '    },',
                '});',
            ];

            expect(detailOf(findings, 'param_missing').example).toEqual({
                language: 'javascript',
                lines: override([
                    '        load(id, ...rest) {',
                    "            return this.$super('load', id, ...rest);",
                    '        },',
                ]),
                broken: 3,
            });
            expect(detailOf(findings, 'method_missing').example?.lines).toEqual(
                override(['        reset() {', "            return this.$super('reset');", '        },']),
            );
        });

        it.each([
            ['computed_missing', "            return this.$super('total');"],
            ['data_missing', '            return this.page;'],
            ['inject_missing', '            return this.acl;'],
            ['ref_missing', '            return this.$refs.root;'],
            ['provide_missing', "    inject: ['card'],"],
            ['prop_missing', '<sw-example-card :title="value" />'],
            ['emit_missing', '<sw-example-card @change="onPluginAction" />'],
            ['slot_missing', '    <template #footer>'],
        ])('uses the member of %s the way a plugin does', (rule, broken) => {
            const { example } = detailOf(explain(EVERY_MEMBER, NO_MEMBER), rule);

            expect(example?.lines[example.broken]).toBe(broken);
        });

        it('has no code for a mixin, whose members a plugin uses in no one way', () => {
            const detail = detailOf(explain(EVERY_MEMBER, NO_MEMBER), 'mixin_missing');

            expect(detail.headline).toBe('The mixin listing is gone');
            expect(detail.example).toBeUndefined();
        });

        it('renders the component without a prop whose default changed, because only then the default counts', () => {
            const findings = explain(
                { options: 'props: { limit: { type: Number, default: 25 } }' },
                { options: 'props: { limit: { type: Number, default: 100 } }' },
            );

            expect(detailOf(findings, 'prop_default_changed')).toMatchObject({
                headline: 'The default of the prop limit changed',
                example: { lines: ['<sw-example-card />'] },
            });
        });

        it('calls a method without a parameter that lost its default or got another one', () => {
            const call = (member: string) => [
                "Shopware.Component.override('sw-example-card', {",
                '    methods: {',
                `        ${member} {`,
                `            return this.$super('load', id);`,
                '        },',
                '    },',
                '});',
            ];
            const lost = explain(methods('load(id, limit = 25, force) {}'), methods('load(id, limit, force) {}'));
            const changed = explain(methods('load(id, limit = 25) {}'), methods('load(id, limit = 50) {}'));

            expect(detailOf(lost, 'param_now_required').example?.lines).toEqual(call('load(id)'));
            expect(detailOf(changed, 'param_default_changed').example?.lines).toEqual(call('load(id)'));
        });

        it('renders the component without a prop that is now required', () => {
            const findings = explain(
                { options: 'props: { title: { type: String, required: false } }' },
                { options: 'props: { title: { type: String, required: true } }' },
            );

            expect(detailOf(findings, 'prop_now_required')).toMatchObject({
                headline: 'The prop title is now required',
                marker: '/** @bc-change PropBecomesRequired version:v6.8.0 */',
                example: { language: 'twig', lines: ['<sw-example-card />'], broken: 0 },
            });
        });
    });

    describe('plugin code that a break of a service or a store breaks', () => {
        it('calls a method of a service and reads a property of it', () => {
            const findings = explainTrees(classTree('    load(id, force) {}\n    name = 1;'), classTree('    load(id) {}'));

            expect(detailOf(findings, 'param_missing').example).toEqual({
                language: 'javascript',
                lines: ["Shopware.Service('exampleService').load(id, force);"],
                broken: 0,
            });
            expect(detailOf(findings, 'property_missing').example?.lines).toEqual([
                "const value = Shopware.Service('exampleService').name;",
            ]);
        });

        it('gets a service that is gone, which has a line at the base commit only', () => {
            const findings = explainTrees(classTree('    load() {}'), treeOf({ [MAIN]: '' }));

            expect(detailOf(findings, 'service_missing')).toEqual({
                headline: 'The service exampleService is gone',
                atBase: { file: SERVICE_FILE, line: 1 },
                isGone: true,
                marker: '/** @deprecated tag:v6.8.0 - Will be removed. */',
                example: { language: 'javascript', lines: ["const value = Shopware.Service('exampleService');"], broken: 0 },
            });
        });

        it('calls an action of a store and reads its state', () => {
            const findings = explainTrees(
                optionsTree('state: () => ({ count: 0 }), actions: { load(id) {} }'),
                optionsTree('state: () => ({}), actions: { }'),
            );

            expect(detailOf(findings, 'action_missing').example?.lines).toEqual(["Shopware.Store.get('example').load(id);"]);
            expect(detailOf(findings, 'state_missing').example?.lines).toEqual([
                "const value = Shopware.Store.get('example').count;",
            ]);
        });
    });

    describe('findings that are no break', () => {
        it('says nothing beyond the summary about an addition', () => {
            const findings = explain(template(''), template('{% block sw_example_card_title %}{% endblock %}'));

            expect(detailOf(findings, 'block_added')).toEqual({});
        });

        it('only gives the line at the base commit for a change that a marker announced', () => {
            const findings = explain(
                methods('/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force) {}'),
                methods('load(id) {}'),
            );

            expect(detailOf(findings, 'announced_change')).toEqual({ atBase: { file: SCRIPT_FILE, line: 5 } });
        });
    });
});
