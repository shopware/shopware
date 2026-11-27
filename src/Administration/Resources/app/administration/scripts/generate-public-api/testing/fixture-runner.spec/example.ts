/**
 * @sw-package framework
 *
 * The example case the specs of the fixture runner share: what one case expects, and the outputs that differ
 * from it in one place.
 */
import { isDeepStrictEqual } from 'node:util';
import type { Finding } from '../../break-check/finding';
import type { SnapshotFiles } from '../../snapshot/snapshot';
import { fixtureTests } from '../fixture-runner';
import type { CaseOutput, ExpectedFailures, FixtureCase } from '../fixture-runner';

const REPORT = [
    '# Component sw-example-card',
    '',
    '```text',
    'component sw-example-card  public  js',
    '',
    'inject',
    '  acl',
    '',
    'props',
    '  title?: string',
    '',
    'emits',
    '  close(...args: any[])',
    '',
    'slots',
    '  default  {}  block=sw_example_card',
    '',
    'data',
    '  isOpen: any',
    '',
    'computed',
    '  hasTitle: any',
    '',
    'methods',
    '  onClose(): any',
    '',
    'blocks',
    '  sw_example_card',
    '    sw_example_card_title  [until v6.8.0]  [scope: item]',
    '',
    'crossings',
    '  div  sw_example_card -> sw_example_card_title',
    '',
    'refs',
    '  title  -> h3',
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
    '            slots: {};',
    '            mixins: readonly [];',
    '            inject: {};',
    '            provide: {};',
    '            data: {};',
    '            computed: {};',
    '            methods: {};',
    '            blocks: {',
    '                sw_example_card: Block<null>;',
    "                sw_example_card_title: Block<'sw_example_card', 'scope:item'>;",
    '            };',
    '            crossings: readonly [',
    "                readonly ['div', 'sw_example_card', 'sw_example_card_title'],",
    '            ];',
    '            refs: {};',
    '        };',
    '    }',
    '}',
    '',
].join('\n');

const SERVICE_REPORT = [
    '# Services of src/app/service/example.service.ts',
    '',
    '```text',
    'service exampleService  public  ts  typed  provider',
    '',
    'properties',
    '  limit: number',
    '',
    'methods',
    '  load(id: string): Promise<any>',
    '```',
    '',
].join('\n');

const STORE_REPORT = [
    '# Stores of src/app/store/example.store.ts',
    '',
    '```text',
    'store example  public  ts  typed  options',
    '',
    'state',
    '  items: string[]',
    '',
    'getters',
    '  hasItems: boolean',
    '',
    'actions',
    '  addItem(item: string): void',
    '```',
    '',
].join('\n');

const INDEX = `${JSON.stringify(
    {
        components: {
            'sw-example-card': { visibility: 'public', blocks: ['sw_example_card', 'sw_example_card_title'] },
        },
        services: {
            exampleService: { file: 'app/service/example.service', visibility: 'public' },
        },
        stores: {
            example: { file: 'app/store/example.store', visibility: 'public' },
        },
        mixins: {},
    },
    null,
    4,
)}\n`;

/** @private */
export const BLOCK_FINDING: Finding = {
    group: 'Break',
    rule: 'block_moved_parent',
    element: 'component:sw-example-card/block:sw_example_card_title',
    states: ['current', 'major'],
    explanation: 'The block moved from sw_example_card_header to sw_example_card.',
    at: 'src/app/component/base/sw-example-card/sw-example-card.html.twig:4',
};

/** @private */
export const CROSSING_FINDING: Finding = {
    group: 'Break',
    rule: 'element_crosses_block',
    element: 'component:sw-example-card/crossing:div@sw_example_card>sw_example_card_title',
    states: ['current', 'major'],
    explanation: 'The div opens in block sw_example_card and closes in block sw_example_card_title.',
};

/**
 * @private
 *
 * One finding per kind of member that the cases expect findings on.
 */
export const MEMBER_FINDINGS: Finding[] = [
    'prop:title',
    'computed:hasTitle',
    'method:onClose',
    'ref:title',
].map((member) => ({
    group: 'Break',
    rule: `${member.split(':')[0]}_missing`,
    element: `component:sw-example-card/${member}`,
    states: ['current', 'major'],
    explanation: 'The member is gone.',
}));

/**
 * @private
 *
 * One finding about the service itself, and one per kind of its members.
 */
export const [SERVICE_FINDING, SERVICE_METHOD_FINDING]: Finding[] = [
    'service:exampleService',
    'service:exampleService/method:load',
    'service:exampleService/property:limit',
].map((element) => ({
    group: 'Break',
    rule: 'visibility_became_private',
    element,
    states: ['current', 'major'],
    explanation: 'It became private.',
}));

const SERVICE_FINDINGS = [
    SERVICE_FINDING,
    SERVICE_METHOD_FINDING,
    { ...SERVICE_METHOD_FINDING, element: 'service:exampleService/property:limit' },
];

/** One finding about the store itself, and one per kind of its members. */
const STORE_FINDINGS: Finding[] = [
    'store:example',
    'store:example/action:addItem',
    'store:example/state:items',
    'store:example/getter:hasItems',
].map((element) => ({
    group: 'Break',
    rule: 'visibility_became_private',
    element,
    states: ['current', 'major'],
    explanation: 'It became private.',
}));

/** @private */
export const FINDINGS = [
    BLOCK_FINDING,
    CROSSING_FINDING,
    ...MEMBER_FINDINGS,
    ...SERVICE_FINDINGS,
    ...STORE_FINDINGS,
];

/** @private */
export const [STORE_FINDING, STORE_ACTION_FINDING] = STORE_FINDINGS;

/** @private */
export const FILES = {
    'app/component/base/sw-example-card.api.md': REPORT,
    'app/component/base/sw-example-card.d.ts': CONTRACT,
    'app/service/example.service.api.md': SERVICE_REPORT,
    'app/store/example.store.api.md': STORE_REPORT,
    'index.json': INDEX,
    'current.d.ts': '/// <reference path="./app/component/base/sw-example-card.d.ts" />\n',
};

/** @private */
export const EXPECTED: CaseOutput = { before: FILES, after: FILES, findings: FINDINGS };

/** @private */
export const FIXTURE: FixtureCase = {
    name: 'example',
    beforeRoot: '/fixtures/example/before',
    afterRoot: '/fixtures/example/after',
    majorFlags: ['v6.8.0.0'],
    expected: EXPECTED,
};

/** @private */
export const NO_FAILURES: ExpectedFailures = { views: [], comparisons: [] };

/**
 * @private
 *
 * The comparison `name` of the example case, against a generator and a break check that produce `output`.
 */
export function comparison(name: string, output: CaseOutput, expectedFailures = NO_FAILURES) {
    const snapshots: Record<string, SnapshotFiles> = {
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

/** @private */
export const VIEWS = [
    'block-tree',
    'block-contract',
    'block-structure',
    'block-findings',
    'component-members',
    'member-contract',
    'member-findings',
    'service-units',
    'service-members',
    'store-units',
    'store-members',
    'complete',
];

function matches(name: string, output: CaseOutput): boolean {
    const { actual, expected } = comparison(name, output);

    return isDeepStrictEqual(actual(), expected);
}

/** @private */
export function failingViews(output: CaseOutput): string[] {
    return VIEWS.filter((view) => !matches(`example/${view}`, output));
}

/** @private */
export function withFile(file: keyof typeof FILES, search: string, replacement: string): CaseOutput {
    const after = { ...FILES, [file]: FILES[file].replace(search, replacement) };

    if (after[file] === FILES[file]) {
        throw new Error(`"${search}" does not occur in ${file}`);
    }

    return { ...EXPECTED, after };
}

/** @private */
export function withFinding(finding: Finding, changes: Partial<Finding>): CaseOutput {
    return {
        ...EXPECTED,
        findings: FINDINGS.map((candidate) => (candidate === finding ? { ...candidate, ...changes } : candidate)),
    };
}

/** @private */
export function withBlockFinding(changes: Partial<Finding>): CaseOutput {
    return withFinding(BLOCK_FINDING, changes);
}

/** @private */
export function inBothSnapshots(output: CaseOutput): CaseOutput {
    return { ...output, before: output.after };
}
