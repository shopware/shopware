/**
 * @sw-package framework
 */
import { isDeepStrictEqual } from 'node:util';
import { fixtureTests } from './fixture-runner';
import type { CaseOutput, ExpectedFailures, FixtureCase } from './fixture-runner';
import type { Finding } from './findings';
import type { Snapshot } from './snapshot';

const REPORT = [
    '# Component sw-example-card',
    '',
    '```text',
    'component sw-example-card  public  js',
    '',
    'props',
    '  title?: string',
    '',
    'blocks',
    '  sw_example_card',
    '    sw_example_card_title  [until v6.8.0]  [scope: item]',
    '',
    'crossings',
    '  div  sw_example_card -> sw_example_card_title',
    '```',
    '',
].join('\n');

const CONTRACT = [
    'declare namespace AdminApi {',
    '    interface Components {',
    "        'sw-example-card': {",
    '            props: {',
    "                title: Prop<string, false, 'literal'>;",
    '            };',
    '            emits: {};',
    '            blocks: {',
    '                sw_example_card: Block<null>;',
    "                sw_example_card_title: Block<'sw_example_card', 'scope:item'>;",
    '            };',
    '            crossings: readonly [',
    "                readonly ['div', 'sw_example_card', 'sw_example_card_title'],",
    '            ];',
    '        };',
    '    }',
    '}',
    '',
].join('\n');

const INDEX = `${JSON.stringify(
    {
        components: {
            'sw-example-card': { visibility: 'public', blocks: ['sw_example_card', 'sw_example_card_title'] },
        },
        mixins: {},
    },
    null,
    4,
)}\n`;

const BLOCK_FINDING: Finding = {
    group: 'Break',
    rule: 'block_moved_parent',
    element: 'component:sw-example-card/block:sw_example_card_title',
    states: ['current', 'major'],
    explanation: 'The block moved from sw_example_card_header to sw_example_card.',
    at: 'src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
};

const CROSSING_FINDING: Finding = {
    group: 'Break',
    rule: 'element_crosses_block',
    element: 'component:sw-example-card/crossing:div@sw_example_card>sw_example_card_title',
    states: ['current', 'major'],
    explanation: 'The div opens in block sw_example_card and closes in block sw_example_card_title.',
};

const CLASS_FINDING: Finding = {
    group: 'Info',
    rule: 'class_missing',
    element: 'component:sw-example-card/class:sw-example-card__title',
    states: ['current', 'major'],
    explanation: 'The CSS class sw-example-card__title is no longer in the template.',
};

const FINDINGS = [
    BLOCK_FINDING,
    CROSSING_FINDING,
    CLASS_FINDING,
];

const FILES = {
    'app/component/base/sw-example-card.api.md': REPORT,
    'app/component/base/sw-example-card.d.ts': CONTRACT,
    'index.json': INDEX,
    'current.d.ts': '/// <reference path="./app/component/base/sw-example-card.d.ts" />\n',
};

const EXPECTED: CaseOutput = { before: FILES, after: FILES, findings: FINDINGS };

const FIXTURE: FixtureCase = {
    name: 'example',
    beforeRoot: '/fixtures/example/before',
    afterRoot: '/fixtures/example/after',
    headVersion: '6.7',
    majorFlags: ['v6.8.0.0'],
    expected: EXPECTED,
};

const NO_FAILURES: ExpectedFailures = { views: [], comparisons: [] };

/**
 * The comparison `name` of the example case, against a generator and a break check that produce `output`.
 */
function comparison(name: string, output: CaseOutput, expectedFailures = NO_FAILURES) {
    const snapshots: Record<string, Snapshot> = {
        [FIXTURE.beforeRoot]: { files: output.before },
        [FIXTURE.afterRoot]: { files: output.after },
    };
    const test = fixtureTests([FIXTURE], expectedFailures, {
        generateSnapshot: (administrationRoot) => snapshots[administrationRoot],
        classifyChanges: () => output.findings,
    }).find((candidate) => candidate.name === name);

    if (test === undefined) {
        throw new Error(`No comparison is named ${name}`);
    }

    return test;
}

const VIEWS = [
    'block-tree',
    'block-structure',
    'block-findings',
    'complete',
];

function matches(name: string, output: CaseOutput): boolean {
    const { actual, expected } = comparison(name, output);

    return isDeepStrictEqual(actual(), expected);
}

function failingViews(output: CaseOutput): string[] {
    return VIEWS.filter((view) => !matches(`example/${view}`, output));
}

function withFile(file: keyof typeof FILES, search: string, replacement: string): CaseOutput {
    const after = { ...FILES, [file]: FILES[file].replace(search, replacement) };

    if (after[file] === FILES[file]) {
        throw new Error(`"${search}" does not occur in ${file}`);
    }

    return { ...EXPECTED, after };
}

function withFinding(finding: Finding, changes: Partial<Finding>): CaseOutput {
    return {
        ...EXPECTED,
        findings: FINDINGS.map((candidate) => (candidate === finding ? { ...candidate, ...changes } : candidate)),
    };
}

function withBlockFinding(changes: Partial<Finding>): CaseOutput {
    return withFinding(BLOCK_FINDING, changes);
}

function inBothSnapshots(output: CaseOutput): CaseOutput {
    return { ...output, before: output.after };
}

describe('fixtureTests', () => {
    it('compares every case per unit kind and as a whole', () => {
        const names = fixtureTests([FIXTURE], NO_FAILURES, {
            generateSnapshot: () => ({ files: {} }),
            classifyChanges: () => [],
        }).map((test) => test.name);

        expect(names).toEqual(VIEWS.map((view) => `example/${view}`));
    });

    it('passes every unit kind and the complete output when they match', () => {
        expect(failingViews(EXPECTED)).toEqual([]);
    });

    it.each([
        [
            'the nesting in the report',
            withFile('app/component/base/sw-example-card.api.md', '    sw_example_card_title', '  sw_example_card_title'),
            ['block-tree', 'complete'],
        ],
        [
            'a flag state marker in the report',
            withFile('app/component/base/sw-example-card.api.md', '[until v6.8.0]', '[from v6.8.0]'),
            ['block-tree', 'complete'],
        ],
        [
            'the parent in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "Block<'sw_example_card', ", 'Block<null, '),
            ['block-tree', 'complete'],
        ],
        [
            'the index',
            withFile('index.json', '"sw_example_card_title"', '"sw_example_card_headline"'),
            ['block-tree', 'complete'],
        ],
        [
            'a scope marker in the report',
            withFile('app/component/base/sw-example-card.api.md', '[scope: item]', '[after v-if]'),
            ['block-structure', 'complete'],
        ],
        [
            'another kind of block context in the report',
            withFile('app/component/base/sw-example-card.api.md', '[scope: item]', '[scope: item]  [slot: default]'),
            ['block-structure', 'complete'],
        ],
        [
            'a scope in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "'scope:item'", "'afterIf'"),
            ['block-structure', 'complete'],
        ],
        [
            'a crossing in the report',
            withFile('app/component/base/sw-example-card.api.md', 'div  sw_example_card', 'span  sw_example_card'),
            ['block-structure', 'complete'],
        ],
        [
            'a crossing in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "'div'", "'span'"),
            ['block-structure', 'complete'],
        ],
        ['the rule of a finding', withBlockFinding({ rule: 'block_missing' }), ['block-findings', 'complete']],
        ['the group of a finding', withBlockFinding({ group: 'Info' }), ['block-findings', 'complete']],
        [
            'the details of a finding',
            withBlockFinding({ at: 'sw-example-card.html.twig:9' }),
            ['block-findings', 'complete'],
        ],
        [
            'a finding on a block scope',
            {
                ...EXPECTED,
                findings: [
                    ...FINDINGS,
                    { ...BLOCK_FINDING, element: `${BLOCK_FINDING.element}/scope:item`, rule: 'block_scope_missing' },
                ],
            },
            ['block-findings', 'complete'],
        ],
        ['a missing snapshot', { ...EXPECTED, before: {} }, ['block-tree', 'block-structure', 'complete']],
        [
            'the props in the report',
            withFile('app/component/base/sw-example-card.api.md', 'title?: string', 'title: string'),
            ['complete'],
        ],
        [
            'the emits in the contract',
            withFile('app/component/base/sw-example-card.d.ts', 'emits: {};', 'emits: { change: [] };'),
            ['complete'],
        ],
        ['the visibility in the index', withFile('index.json', '"public"', '"private"'), ['complete']],
        ['a finding on a class', withFinding(CLASS_FINDING, { rule: 'class_added' }), ['complete']],
        ['an entry point', withFile('current.d.ts', 'sw-example-card.d.ts', 'sw-example-panel.d.ts'), ['complete']],
    ])('fails only the views that cover a difference in %s', (_, output, expectedFailingViews) => {
        expect(failingViews(output)).toEqual(expectedFailingViews);
    });

    it('marks every comparison of a view on the expected-fail list', () => {
        const expectedFailures = { views: ['block-tree'], comparisons: [] };

        expect(comparison('example/block-tree', EXPECTED, expectedFailures).expectedToFail).toBe(true);
        expect(comparison('example/block-structure', EXPECTED, expectedFailures).expectedToFail).toBe(false);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(false);
    });

    it('marks a single comparison on the expected-fail list', () => {
        const expectedFailures = { views: [], comparisons: ['example/complete'] };

        expect(comparison('example/block-tree', EXPECTED, expectedFailures).expectedToFail).toBe(false);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(true);
    });

    it('keeps an expected failure marked once its output matches, which it.failing reports as a failure', () => {
        const { expectedToFail, actual, expected } = comparison('example/block-tree', EXPECTED, {
            views: [],
            comparisons: ['example/block-tree'],
        });

        expect(expectedToFail).toBe(true);
        expect(actual()).toEqual(expected);
    });

    it('runs the generator and the break check once per case', () => {
        const generateSnapshot = jest.fn(() => ({ files: FILES }));
        const classifyChanges = jest.fn(() => FINDINGS);

        fixtureTests([FIXTURE], NO_FAILURES, { generateSnapshot, classifyChanges }).forEach(({ actual, expected }) => {
            expect(actual()).toEqual(expected);
        });

        expect(generateSnapshot.mock.calls).toEqual([
            [FIXTURE.beforeRoot, { majorFlags: ['v6.8.0.0'] }],
            [FIXTURE.afterRoot, { majorFlags: ['v6.8.0.0'] }],
        ]);
        expect(classifyChanges).toHaveBeenCalledTimes(1);
        expect(classifyChanges).toHaveBeenCalledWith({ files: FILES }, { files: FILES }, { headVersion: '6.7' });
    });

    it.each([
        [
            'report section blocks for the unit kind block-tree',
            inBothSnapshots(withFile('app/component/base/sw-example-card.api.md', 'blocks\n', 'slots\n')),
        ],
        [
            'index key blocks for the unit kind block-tree',
            inBothSnapshots(withFile('index.json', '"blocks"', '"slots"')),
        ],
        [
            'report section block-context for the unit kind block-structure',
            inBothSnapshots(withFile('app/component/base/sw-example-card.api.md', '  [scope: item]', '')),
        ],
        [
            'contract member blockContext for the unit kind block-structure',
            inBothSnapshots(withFile('app/component/base/sw-example-card.d.ts', ", 'scope:item'", '')),
        ],
        [
            'contract member crossings for the unit kind block-structure',
            inBothSnapshots(withFile('app/component/base/sw-example-card.d.ts', 'crossings:', 'refs:')),
        ],
        [
            'findings on crossing elements for the unit kind block-findings',
            { ...EXPECTED, findings: [BLOCK_FINDING, CLASS_FINDING] },
        ],
    ])('rejects a part that occurs in no expected output, which would compare nothing: %s', (part, expected) => {
        expect(() =>
            fixtureTests([{ ...FIXTURE, expected }], NO_FAILURES, {
                generateSnapshot: () => ({ files: {} }),
                classifyChanges: () => [],
            }),
        ).toThrow(`No fixture case has expected ${part}.`);
    });

    it.each([
        [
            'an unknown view',
            { views: ['removed-kind'], comparisons: [] },
            'The expected-fail list names views that do not exist: removed-kind.',
        ],
        [
            'a comparison of a removed case',
            { views: [], comparisons: ['removed-case/block-tree'] },
            'The expected-fail list names comparisons that do not exist: removed-case/block-tree.',
        ],
        [
            'a comparison of an unknown view',
            { views: [], comparisons: ['example/blocks'] },
            'The expected-fail list names comparisons that do not exist: example/blocks.',
        ],
        [
            'a comparison whose view is listed',
            { views: ['block-tree'], comparisons: ['example/block-tree'] },
            'The expected-fail list names comparisons whose view it lists already: example/block-tree.',
        ],
    ])('rejects %s on the expected-fail list', (_, expectedFailures, message) => {
        expect(() => comparison('example/block-tree', EXPECTED, expectedFailures)).toThrow(message);
    });
});
