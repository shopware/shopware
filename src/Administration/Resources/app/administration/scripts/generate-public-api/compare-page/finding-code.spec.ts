/**
 * @sw-package framework
 */
import { explainSourceTrees } from '../break-check/break-check';
import type { ExplainedFinding } from '../break-check/finding';
import type { SourceTree } from '../source/source-tree';
import { cardTree, OPTIONS } from '../testing/example-components';
import { changedCode, fixCode } from './finding-code';
import type { CodeBlock } from './finding-code';

const SCRIPT = 'src/module/sw-example/component/sw-example-card/index.js';
const TEMPLATE = 'src/module/sw-example/component/sw-example-card/sw-example-card.html.twig';

/** One line per line of the block, as a diff prints it: both line numbers, the sign and the text. */
function printed(block: CodeBlock | undefined): string[] | undefined {
    return block?.lines.map(({ type, before, after, text }) =>
        `${String(before ?? '').padStart(2)} ${String(after ?? '').padStart(2)} ${type} ${text}`.trimEnd(),
    );
}

/** The code of the one finding of `rule` for the change from `base` to `head`. */
function codeOf(rule: string, base: SourceTree, head: SourceTree) {
    const findings = explainSourceTrees(base, head, OPTIONS.majorFlags).filter((finding) => finding.rule === rule);

    expect(findings).toHaveLength(1);

    const [finding]: ExplainedFinding[] = findings;

    return { finding, changed: changedCode(finding, base, head), fix: fixCode(finding, base, head) };
}

function methods(...declarations: string[]): SourceTree {
    return cardTree({ options: `methods: {\n${declarations.join('\n')}\n}` });
}

function template(...lines: string[]): SourceTree {
    return cardTree({ template: ['{% block sw_example_card %}', ...lines, '{% endblock %}'].join('\n') });
}

describe('scripts/generate-public-api/compare-page/finding-code', () => {
    describe('the changed code', () => {
        it('shows the change of the line of the element with both line numbers and the lines around it', () => {
            const { changed } = codeOf(
                'param_missing',
                methods('    load(id, force) {', '        return id;', '    },', '    reset() {},'),
                methods('    load(id) {', '        return id;', '    },', '    reset() {},'),
            );

            expect(changed?.file).toBe(SCRIPT);
            expect(printed(changed)).toEqual([
                ' 3  3   export default { template, methods: {',
                ' 4    -     load(id, force) {',
                '    4 +     load(id) {',
                ' 5  5           return id;',
                ' 6  6       },',
            ]);
        });

        it('shows the lines of an element that is gone', () => {
            const { changed } = codeOf(
                'block_missing',
                template('<h2>', '{% block sw_example_card_title %}{{ title }}{% endblock %}', '</h2>'),
                template('<h2>', '{{ title }}', '</h2>'),
            );

            expect(printed(changed)).toEqual([
                ' 1  1   {% block sw_example_card %}',
                ' 2  2   <h2>',
                ' 3    - {% block sw_example_card_title %}{{ title }}{% endblock %}',
                '    3 + {{ title }}',
                ' 4  4   </h2>',
                ' 5  5   {% endblock %}',
            ]);
        });

        it('shows a change in the lines below the line of the element, which belong to its declaration', () => {
            const props = (required: boolean) =>
                cardTree({
                    options: [
                        'props: {',
                        '    title: {',
                        '        type: String,',
                        `        required: ${required},`,
                        '    },',
                        '}',
                    ].join('\n'),
                });
            const { changed } = codeOf('prop_now_required', props(false), props(true));

            expect(printed(changed)).toEqual([
                ' 4  4       title: {',
                ' 5  5           type: String,',
                ' 6    -         required: false,',
                '    6 +         required: true,',
                ' 7  7       },',
                ' 8  8   } };',
            ]);
        });

        it('shows the closest changes that name the element, and its own line between them, when nothing around it changed', () => {
            const item = '{% block sw_example_card_item %}{{ item.name }}{% endblock %}';
            const filler = Array.from({ length: 8 }, (_, index) => `<p>${index}</p>`);
            const { finding, changed } = codeOf(
                'block_scope_missing',
                template('<li v-for="(item, index) in items">', ...filler, item, ...filler, '<i>{{ index }}</i>', '</li>'),
                template('<li v-for="item in items">', ...filler, item, ...filler, '<i>{{ item.id }}</i>', '</li>'),
            );

            expect(finding.at).toBe(`${TEMPLATE}:11`);
            expect(printed(changed)).toEqual([
                ' 1  1   {% block sw_example_card %}',
                ' 2    - <li v-for="(item, index) in items">',
                '    2 + <li v-for="item in items">',
                ' 3  3   <p>0</p>',
                ' 4  4   <p>1</p>',
                '      …',
                `11 11   ${item}`,
                '      …',
                '18 18   <p>6</p>',
                '19 19   <p>7</p>',
                '20    - <i>{{ index }}</i>',
                '   20 + <i>{{ item.id }}</i>',
                '21 21   </li>',
                '22 22   {% endblock %}',
            ]);
        });

        it('cuts a long change down to the lines around the one of the element and says where it is cut', () => {
            const others = Array.from({ length: 40 }, (_, index) => `    other${index}() {},`);
            const base = methods(...others.slice(0, 20), '    load() {},', ...others.slice(20), '    reset() {},');
            const head = methods('    reset() {},');
            const load = explainSourceTrees(base, head, OPTIONS.majorFlags).find(({ element }) =>
                element.endsWith('method:load'),
            );
            const lines = printed(load && changedCode(load, base, head)) ?? [];

            expect(lines).toHaveLength(19);
            expect(lines[0]).toBe('      …');
            expect(lines[9]).toBe('24    -     load() {},');
            expect(lines[18]).toBe('      …');
        });

        it('is missing when the changes of the file are neither near the element nor name it', () => {
            const filler = Array.from({ length: 12 }, (_, index) => `<p>${index}</p>`);
            const base = template(
                '<li v-for="(item, index) in items">',
                '{% block sw_example_card_item %}{% endblock %}',
                '</li>',
                ...filler,
                '<b>old</b>',
            );
            const head = template(
                '<li v-for="(item, index) in items">',
                '{% block sw_example_card_item %}{% endblock %}',
                '</li>',
                ...filler,
                '<b>new</b>',
            );
            const [scope] = explainSourceTrees(
                base,
                template(
                    '<li v-for="item in items">',
                    '{% block sw_example_card_item %}{% endblock %}',
                    '</li>',
                    ...filler,
                    '<b>old</b>',
                ),
                OPTIONS.majorFlags,
            );

            expect(scope.rule).toBe('block_scope_missing');
            expect(changedCode(scope, base, head)).toBeUndefined();
        });
    });

    describe('the code of the fix', () => {
        it('puts the comment above the line of the element as it reads at the head commit, with its indentation', () => {
            const item = '    {% block sw_example_card_item %}{{ item.name }}{% endblock %}';
            const scope = codeOf(
                'block_scope_missing',
                template('<li v-for="(item, index) in items">', item, '</li>'),
                template('<li v-for="item in items">', item, '</li>'),
            );
            const parameter = codeOf('param_missing', methods('    load(id, force) {},'), methods('    load(id) {},'));

            expect(scope.fix?.file).toBe(TEMPLATE);
            expect(scope.fix?.lines).toEqual([
                { type: '+', text: '    {# @bc-change ScopeVariableRemoval version:v6.8.0 variable:index #}' },
                { type: ' ', text: item },
            ]);
            expect(parameter.fix?.lines).toEqual([
                { type: '+', text: '    /** @bc-change ParameterRemoval version:v6.8.0 parameter:force */' },
                { type: ' ', text: '    load(id) {},' },
            ]);
        });

        it('puts it above the line that an element that is gone had at the base commit', () => {
            const { finding, fix } = codeOf(
                'method_missing',
                methods('    load() {},', '    reset() {},'),
                methods('    reset() {},'),
            );

            expect(finding.at).toBe(`${SCRIPT}:3`);
            expect(fix?.lines).toEqual([
                { type: '+', text: '    /** @deprecated tag:v6.8.0 - Will be removed. */' },
                { type: ' ', text: '    load() {},' },
            ]);
        });

        it('is missing for a break that no comment announces', () => {
            const { fix } = codeOf(
                'method_signature_changed',
                methods('    load() { return 1; },'),
                methods('    async load() { return 1; },'),
            );

            expect(fix).toBeUndefined();
        });
    });
});
