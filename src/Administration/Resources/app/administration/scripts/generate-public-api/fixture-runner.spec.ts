/**
 * @sw-package framework
 */
import { isDeepStrictEqual } from 'node:util';
import { fixtureTests } from './fixture-runner';
import type { CaseOutput, FixtureCase } from './fixture-runner';
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

const FINDINGS = [
    'Public API check failed: 1 unannounced break.',
    '',
    'Break (1)',
    '  block_moved_parent  component:sw-example-card/block:sw_example_card_title  current, major',
    '    The block moved from sw_example_card_header to sw_example_card.',
    '    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
    '',
    'Info (1)',
    '  class_missing  component:sw-example-card/class:sw-example-card__title  current, major',
    '    The CSS class sw-example-card__title is no longer in the template.',
    '',
].join('\n');

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

/**
 * The comparison `name` of the example case, against a generator and a break check that produce `output`.
 */
function comparison(name: string, output: CaseOutput, expectedFailures: string[] = []) {
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

    expect(after[file]).not.toBe(FILES[file]);

    return { ...EXPECTED, after };
}

describe('fixtureTests', () => {
    it('compares every case per unit kind and as a whole', () => {
        const names = fixtureTests([FIXTURE], [], {
            generateSnapshot: () => ({ files: {} }),
            classifyChanges: () => '',
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
        ['the findings', { ...EXPECTED, findings: FINDINGS.replace('block_moved_parent', 'block_missing') }],
        ['the group of a finding', { ...EXPECTED, findings: FINDINGS.replace('Break (1)', 'Allowed (1)') }],
        ['the details of a finding', { ...EXPECTED, findings: FINDINGS.replace('.html.twig:4', '.html.twig:9') }],
        ['a missing snapshot', { ...EXPECTED, before: {} }],
    ])('fails a unit kind that differs in %s', (_, output) => {
        expect(matches('example/blocks', output)).toBe(false);
        expect(matches('example/complete', output)).toBe(false);
    });

    it.each([
        ['the report', withFile('app/component/base/sw-example-card.api.md', 'title?: string', 'title: string')],
        ['the contract', withFile('app/component/base/sw-example-card.d.ts', 'emits: {};', 'emits: { change: [] };')],
        ['the index', withFile('index.json', '"public"', '"private"')],
        ['the findings', { ...EXPECTED, findings: FINDINGS.replace('class_missing', 'class_added') }],
        ['the verdict', { ...EXPECTED, findings: FINDINGS.replace('1 unannounced break', '2 unannounced breaks') }],
        ['an entry point', withFile('current.d.ts', 'sw-example-card.d.ts', 'sw-example-panel.d.ts')],
    ])('passes a unit kind while another part of %s differs', (_, output) => {
        expect(matches('example/blocks', output)).toBe(true);
        expect(matches('example/complete', output)).toBe(false);
    });

    it('marks only the comparisons on the expected-fail list', () => {
        const output = { ...EXPECTED, findings: '' };

        expect(comparison('example/blocks', output, ['example/blocks']).expectedToFail).toBe(true);
        expect(comparison('example/complete', output, ['example/blocks']).expectedToFail).toBe(false);
    });

    it('keeps an expected failure marked once its output matches, which it.failing reports as a failure', () => {
        const { expectedToFail, actual, expected } = comparison('example/blocks', EXPECTED, ['example/blocks']);

        expect(expectedToFail).toBe(true);
        expect(actual()).toEqual(expected);
    });

    it('runs the generator and the break check once per case', () => {
        const generateSnapshot = jest.fn(() => ({ files: FILES }));
        const classifyChanges = jest.fn(() => FINDINGS);

        fixtureTests([FIXTURE], [], { generateSnapshot, classifyChanges }).forEach(({ actual, expected }) => {
            expect(actual()).toEqual(expected);
        });

        expect(generateSnapshot.mock.calls).toEqual([
            [FIXTURE.beforeRoot, { majorFlags: ['v6.8.0.0'] }],
            [FIXTURE.afterRoot, { majorFlags: ['v6.8.0.0'] }],
        ]);
        expect(classifyChanges).toHaveBeenCalledTimes(1);
        expect(classifyChanges).toHaveBeenCalledWith({ files: FILES }, { files: FILES }, { headVersion: '6.7' });
    });

    it('rejects a unit kind that no expected snapshot contains, which would compare nothing', () => {
        const withoutBlocks = { 'current.d.ts': FILES['current.d.ts'] };
        const fixture = { ...FIXTURE, expected: { ...EXPECTED, before: withoutBlocks, after: withoutBlocks } };

        expect(() =>
            fixtureTests([fixture], [], { generateSnapshot: () => ({ files: {} }), classifyChanges: () => '' }),
        ).toThrow('No fixture case has expected output for the unit kind: blocks');
    });

    it.each([
        'removed-case/blocks',
        'example/removed-kind',
    ])('rejects the expected-fail entry %s, which matches no comparison', (entry) => {
        expect(() => comparison('example/blocks', EXPECTED, [entry])).toThrow(
            `The expected-fail list names comparisons that do not exist: ${entry}`,
        );
    });
});
