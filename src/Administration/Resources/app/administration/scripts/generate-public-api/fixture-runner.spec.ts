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
    '    sw_example_card_title',
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
    "                sw_example_card_title: Block<'sw_example_card'>;",
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

const CLASS_FINDING: Finding = {
    group: 'Info',
    rule: 'class_missing',
    element: 'component:sw-example-card/class:sw-example-card__title',
    states: ['current', 'major'],
    explanation: 'The CSS class sw-example-card__title is no longer in the template.',
};

const FINDINGS = [
    BLOCK_FINDING,
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

function matches(name: string, output: CaseOutput): boolean {
    const { actual, expected } = comparison(name, output);

    return isDeepStrictEqual(actual(), expected);
}

function withFile(file: keyof typeof FILES, search: string, replacement: string): CaseOutput {
    const after = { ...FILES, [file]: FILES[file].replace(search, replacement) };

    if (after[file] === FILES[file]) {
        throw new Error(`"${search}" does not occur in ${file}`);
    }

    return { ...EXPECTED, after };
}

function withBlockFinding(changes: Partial<Finding>): CaseOutput {
    return { ...EXPECTED, findings: [{ ...BLOCK_FINDING, ...changes }, CLASS_FINDING] };
}

describe('fixtureTests', () => {
    it('compares every case per unit kind and as a whole', () => {
        const names = fixtureTests([FIXTURE], NO_FAILURES, {
            generateSnapshot: () => ({ files: {} }),
            classifyChanges: () => [],
        }).map((test) => test.name);

        expect(names).toEqual(['example/blocks', 'example/complete']);
    });

    it('passes a unit kind and the complete output that match', () => {
        expect(matches('example/blocks', EXPECTED)).toBe(true);
        expect(matches('example/complete', EXPECTED)).toBe(true);
    });

    it.each([
        [
            'the report',
            withFile('app/component/base/sw-example-card.api.md', '    sw_example_card_title', '  sw_example_card_title'),
        ],
        [
            'a crossing in the report',
            withFile('app/component/base/sw-example-card.api.md', 'div  sw_example_card', 'span  sw_example_card'),
        ],
        ['the contract', withFile('app/component/base/sw-example-card.d.ts', "Block<'sw_example_card'>", 'Block<null>')],
        ['a crossing in the contract', withFile('app/component/base/sw-example-card.d.ts', "'div'", "'span'")],
        ['the index', withFile('index.json', '"sw_example_card_title"', '"sw_example_card_headline"')],
        ['the rule of a finding', withBlockFinding({ rule: 'block_missing' })],
        ['the group of a finding', withBlockFinding({ group: 'Info' })],
        ['the details of a finding', withBlockFinding({ at: 'sw-example-card.html.twig:9' })],
        [
            'a finding on a block scope',
            {
                ...EXPECTED,
                findings: [
                    ...FINDINGS,
                    { ...BLOCK_FINDING, element: `${BLOCK_FINDING.element}/scope:item`, rule: 'block_scope_missing' },
                ],
            },
        ],
        ['a missing snapshot', { ...EXPECTED, before: {} }],
    ])('fails a unit kind that differs in %s', (_, output) => {
        expect(matches('example/blocks', output)).toBe(false);
        expect(matches('example/complete', output)).toBe(false);
    });

    it.each([
        ['the report', withFile('app/component/base/sw-example-card.api.md', 'title?: string', 'title: string')],
        ['the contract', withFile('app/component/base/sw-example-card.d.ts', 'emits: {};', 'emits: { change: [] };')],
        ['the index', withFile('index.json', '"public"', '"private"')],
        ['the findings', { ...EXPECTED, findings: [BLOCK_FINDING, { ...CLASS_FINDING, rule: 'class_added' }] }],
        ['an entry point', withFile('current.d.ts', 'sw-example-card.d.ts', 'sw-example-panel.d.ts')],
    ])('passes a unit kind while another part of %s differs', (_, output) => {
        expect(matches('example/blocks', output)).toBe(true);
        expect(matches('example/complete', output)).toBe(false);
    });

    it('marks every comparison of a view on the expected-fail list', () => {
        const expectedFailures = { views: ['blocks'], comparisons: [] };

        expect(comparison('example/blocks', EXPECTED, expectedFailures).expectedToFail).toBe(true);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(false);
    });

    it('marks a single comparison on the expected-fail list', () => {
        const expectedFailures = { views: [], comparisons: ['example/complete'] };

        expect(comparison('example/blocks', EXPECTED, expectedFailures).expectedToFail).toBe(false);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(true);
    });

    it('keeps an expected failure marked once its output matches, which it.failing reports as a failure', () => {
        const { expectedToFail, actual, expected } = comparison('example/blocks', EXPECTED, {
            views: [],
            comparisons: ['example/blocks'],
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
            'snapshot files',
            {
                ...EXPECTED,
                before: { 'current.d.ts': FILES['current.d.ts'] },
                after: { 'current.d.ts': FILES['current.d.ts'] },
            },
        ],
        ['findings', { ...EXPECTED, findings: [CLASS_FINDING] }],
    ])('rejects a unit kind whose %s occur in no expected output, which would compare nothing', (part, expected) => {
        expect(() =>
            fixtureTests([{ ...FIXTURE, expected }], NO_FAILURES, {
                generateSnapshot: () => ({ files: {} }),
                classifyChanges: () => [],
            }),
        ).toThrow(`No fixture case has expected ${part} for the unit kind blocks.`);
    });

    it.each([
        [
            'an unknown view',
            { views: ['removed-kind'], comparisons: [] },
            'The expected-fail list names views that do not exist: removed-kind.',
        ],
        [
            'a comparison of a removed case',
            { views: [], comparisons: ['removed-case/blocks'] },
            'The expected-fail list names comparisons that do not exist: removed-case/blocks.',
        ],
        [
            'a comparison of an unknown view',
            { views: [], comparisons: ['example/removed-kind'] },
            'The expected-fail list names comparisons that do not exist: example/removed-kind.',
        ],
        [
            'a comparison whose view is listed',
            { views: ['blocks'], comparisons: ['example/blocks'] },
            'The expected-fail list names comparisons whose view it lists already: example/blocks.',
        ],
    ])('rejects %s on the expected-fail list', (_, expectedFailures, message) => {
        expect(() => comparison('example/blocks', EXPECTED, expectedFailures)).toThrow(message);
    });
});
