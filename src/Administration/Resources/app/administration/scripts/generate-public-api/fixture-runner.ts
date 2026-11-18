/**
 * @sw-package framework
 *
 * Compares what the generator and the break check produce for a fixture case with the case's `expected/`
 * folder. The expected files hold every unit kind, but the generator is built one kind at a time, so each
 * case is compared once per unit kind and once as a whole.
 */
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';
import type { ClassifyChanges } from './break-check';
import { elementKind } from './findings';
import type { Finding } from './findings';
import type { GenerateSnapshot } from './snapshot';

/**
 * @private
 *
 * What one case produces: the files of both snapshots and the findings of the break check.
 */
export interface CaseOutput {
    before: Record<string, string>;
    after: Record<string, string>;
    findings: Finding[];
}

/** @private */
export interface FixtureCase {
    name: string;
    beforeRoot: string;
    afterRoot: string;
    headVersion: string;
    majorFlags: string[];
    expected: CaseOutput;
}

/**
 * @private
 *
 * A case as read from its folder, with `findings.txt`, which prints `expected.findings`.
 */
export interface LoadedFixtureCase extends FixtureCase {
    expectedSummary: string;
}

/**
 * @private
 *
 * One comparison of one case: `actual()` has to equal `expected`. `view` is a unit kind or `complete`,
 * and `name` is `<case>/<view>`, the form the expected-fail list uses.
 */
export interface FixtureTest {
    name: string;
    view: string;
    expectedToFail: boolean;
    actual: () => unknown;
    expected: unknown;
}

/**
 * @private
 *
 * The comparisons that cannot pass yet. A view is listed while no case passes it. Once some cases pass,
 * the view leaves the list and the cases that still fail it are listed as single comparisons.
 */
export interface ExpectedFailures {
    views: readonly string[];
    comparisons: readonly string[];
}

/**
 * @private
 *
 * The generator and the break check that the comparisons run.
 */
export interface PublicApiTools {
    generateSnapshot: GenerateSnapshot;
    classifyChanges: ClassifyChanges;
}

interface UnitKind {
    /** Sections of the outline in an `.api.md` report. */
    reportSections: readonly string[];
    /** Members of a registry entry in a `.d.ts` contract. */
    contractMembers: readonly string[];
    /** Keys of a component entry in `index.json`. */
    indexKeys: readonly string[];
    /** Kinds of the elements a finding is about, the `block` in `component:sw-card/block:sw_card_header`. */
    elementKinds: readonly string[];
}

const NO_PARTS: UnitKind = { reportSections: [], contractMembers: [], indexKeys: [], elementKinds: [] };

const PART_LABELS: Record<keyof UnitKind, (value: string) => string> = {
    reportSections: (section) => `report section ${section}`,
    contractMembers: (member) => `contract member ${member}`,
    indexKeys: (key) => `index key ${key}`,
    elementKinds: (kind) => `findings on ${kind} elements`,
};

/*
 * The markers of a block line other than its flag state, such as `[after v-if]` and `[scope: item]`, belong to
 * the block structure, not to the tree. The comparison moves them out of the `blocks` section and member into
 * parts of their own, which exist only here and not in the snapshot files.
 */
const BLOCK_CONTEXT = { reportSection: 'block-context', contractMember: 'blockContext' } as const;

// The block tree, the block structure and the findings on blocks are built in separate steps.
const UNIT_KINDS: Record<string, UnitKind> = {
    'block-tree': {
        ...NO_PARTS,
        reportSections: ['blocks'],
        contractMembers: ['blocks'],
        indexKeys: ['blocks'],
    },
    'block-structure': {
        ...NO_PARTS,
        reportSections: [
            BLOCK_CONTEXT.reportSection,
            'crossings',
        ],
        contractMembers: [
            BLOCK_CONTEXT.contractMember,
            'crossings',
        ],
    },
    'block-findings': {
        ...NO_PARTS,
        elementKinds: [
            'block',
            'crossing',
        ],
    },
};

const REPORT_OUTLINE = /```text\n([\s\S]*?)```/;

const LINE_MARKERS = /\s+\[[^\]]+\]/g;

const FLAG_STATE_MARKER = /^\s+\[(?:from|until) v/;

function sectionName(section: string): string {
    return section.trimStart().split(/\s/, 1)[0];
}

function isContextMarker(marker: string): boolean {
    return !FLAG_STATE_MARKER.test(marker);
}

/**
 * Splits `sw_card_title  [until v6.8.0]  [scope: item]` into `sw_card_title  [until v6.8.0]` for the tree and
 * `sw_card_title  [scope: item]` for the structure.
 */
function splitBlockContextSection(section: string): string[] {
    const [heading, ...lines] = section.split('\n');
    const treeLines = [heading];
    const contextLines = [];

    for (const line of lines) {
        treeLines.push(line.replace(LINE_MARKERS, (marker) => (isContextMarker(marker) ? '' : marker)));

        const contextMarkers = line.match(LINE_MARKERS)?.filter(isContextMarker) ?? [];
        if (contextMarkers.length > 0) {
            contextLines.push(`  ${sectionName(line)}${contextMarkers.join('')}`);
        }
    }

    const tree = treeLines.join('\n');
    if (contextLines.length === 0) {
        return [tree];
    }

    return [tree, [BLOCK_CONTEXT.reportSection, ...contextLines].join('\n')];
}

const REPORT_SECTION_SPLITS: Partial<Record<string, (section: string) => string[]>> = {
    blocks: splitBlockContextSection,
};

function reportSections(report: string, sections: readonly string[]): string {
    const outline = REPORT_OUTLINE.exec(report)?.[1] ?? '';

    return outline
        .split('\n\n')
        .flatMap((section) => REPORT_SECTION_SPLITS[sectionName(section)]?.(section) ?? [section])
        .filter((section) => sections.includes(sectionName(section)))
        .join('\n\n');
}

function interfacesIn(node: ts.Node): ts.InterfaceDeclaration[] {
    if (ts.isInterfaceDeclaration(node)) {
        return [node];
    }

    const found: ts.InterfaceDeclaration[] = [];
    ts.forEachChild(node, (child) => {
        found.push(...interfacesIn(child));
    });

    return found;
}

interface ContractMember {
    name: string;
    text: string;
}

function typeLiteralMembers(member: ts.TypeElement): ts.TypeElement[] {
    return ts.isPropertySignature(member) && member.type && ts.isTypeLiteralNode(member.type)
        ? [...member.type.members]
        : [];
}

function blockTypeArguments(block: ts.TypeElement, source: ts.SourceFile): string[] {
    if (!ts.isPropertySignature(block) || !block.type || !ts.isTypeReferenceNode(block.type)) {
        return [];
    }

    return block.type.typeArguments?.map((argument) => argument.getText(source)) ?? [];
}

/**
 * Splits `sw_card_title: Block<'sw_card', 'afterIf'>` into `sw_card_title: Block<'sw_card'>` for the tree and
 * `sw_card_title: 'afterIf'` for the structure.
 */
function splitBlockContextMember(blocks: ts.TypeElement, source: ts.SourceFile): ContractMember[] {
    const treeLines = ['blocks: {'];
    const contextLines = [];

    for (const block of typeLiteralMembers(blocks)) {
        const name = block.name?.getText(source);
        const [parent, ...context] = blockTypeArguments(block, source);

        treeLines.push(parent === undefined ? block.getText(source) : `${name}: Block<${parent}>;`);
        if (context.length > 0) {
            contextLines.push(`${name}: ${context.join(', ')};`);
        }
    }

    const tree = { name: 'blocks', text: [...treeLines, '};'].join('\n') };
    if (contextLines.length === 0) {
        return [tree];
    }

    const { contractMember } = BLOCK_CONTEXT;

    return [tree, { name: contractMember, text: [`${contractMember}: {`, ...contextLines, '};'].join('\n') }];
}

const CONTRACT_MEMBER_SPLITS: Partial<Record<string, (member: ts.TypeElement, source: ts.SourceFile) => ContractMember[]>> =
    {
        blocks: splitBlockContextMember,
    };

/**
 * The members of the registry entries in a contract, for example `blocks` in
 * `interface Components { 'sw-card': { blocks: {...} } }`.
 */
function contractMembers(contract: string, members: readonly string[]): string {
    const source = ts.createSourceFile('contract.d.ts', contract, ts.ScriptTarget.Latest, true);

    return interfacesIn(source)
        .flatMap((registry) => [...registry.members])
        .flatMap(typeLiteralMembers)
        .flatMap((member): ContractMember[] => {
            const name = member.name?.getText(source);

            if (name === undefined) {
                return [];
            }

            return CONTRACT_MEMBER_SPLITS[name]?.(member, source) ?? [{ name, text: member.getText(source) }];
        })
        .filter(({ name }) => members.includes(name))
        .map(({ text }) => text)
        .join('\n');
}

function indexEntries(index: string, keys: readonly string[]): string {
    const { components = {} } = JSON.parse(index) as { components?: Record<string, Record<string, unknown>> };
    const entries = Object.entries(components)
        .map(([name, entry]) => [
            name,
            Object.fromEntries(keys.filter((key) => Object.hasOwn(entry, key)).map((key) => [key, entry[key]])),
        ])
        .filter(([, entry]) => Object.keys(entry).length > 0);

    return entries.length > 0 ? JSON.stringify(Object.fromEntries(entries), null, 4) : '';
}

const FILE_SLICES: { matches: (file: string) => boolean; slice: (content: string, kind: UnitKind) => string }[] = [
    { matches: (file) => file.endsWith('.api.md'), slice: (content, kind) => reportSections(content, kind.reportSections) },
    { matches: (file) => file.endsWith('.d.ts'), slice: (content, kind) => contractMembers(content, kind.contractMembers) },
    { matches: (file) => file === 'index.json', slice: (content, kind) => indexEntries(content, kind.indexKeys) },
];

/**
 * The part of every snapshot file that belongs to the unit kind. Files without such a part are left out.
 */
function sliceSnapshot(files: Record<string, string>, kind: UnitKind): Record<string, string> {
    return Object.fromEntries(
        Object.entries(files)
            .map(([file, content]): [string, string] => [
                file,
                FILE_SLICES.find(({ matches }) => matches(file))?.slice(content, kind) ?? '',
            ])
            .filter(([, slice]) => slice !== ''),
    );
}

function sliceFindings(findings: Finding[], kind: UnitKind): Finding[] {
    return findings.filter((finding) => kind.elementKinds.includes(elementKind(finding) ?? ''));
}

function sliceCase(output: CaseOutput, kind: UnitKind): CaseOutput {
    return {
        before: sliceSnapshot(output.before, kind),
        after: sliceSnapshot(output.after, kind),
        findings: sliceFindings(output.findings, kind),
    };
}

const VIEWS: Record<string, (output: CaseOutput) => CaseOutput> = {
    ...Object.fromEntries(
        Object.entries(UNIT_KINDS).map(([name, kind]) => [name, (output: CaseOutput) => sliceCase(output, kind)]),
    ),
    complete: (output) => output,
};

function readTree(root: string): Record<string, string> {
    return Object.fromEntries(
        fs
            .readdirSync(root, { recursive: true, encoding: 'utf8' })
            .filter((file) => fs.statSync(path.join(root, file)).isFile())
            .sort()
            .map((file) => [file.split(path.sep).join('/'), fs.readFileSync(path.join(root, file), 'utf8')]),
    );
}

function readJson<Content>(file: string): Content {
    return JSON.parse(fs.readFileSync(file, 'utf8')) as Content;
}

/**
 * @private
 *
 * Reads every folder below `fixturesRoot` that has a `case.json`.
 */
export function loadFixtureCases(fixturesRoot: string): LoadedFixtureCase[] {
    return fs
        .readdirSync(fixturesRoot)
        .filter((name) => fs.existsSync(path.join(fixturesRoot, name, 'case.json')))
        .sort()
        .map((name) => {
            const caseRoot = path.join(fixturesRoot, name);
            const { headVersion, majorFlags } = readJson<{ headVersion: string; majorFlags: string[] }>(
                path.join(caseRoot, 'case.json'),
            );

            return {
                name,
                beforeRoot: path.join(caseRoot, 'before'),
                afterRoot: path.join(caseRoot, 'after'),
                headVersion,
                majorFlags,
                expected: {
                    before: readTree(path.join(caseRoot, 'expected/before')),
                    after: readTree(path.join(caseRoot, 'expected/after')),
                    findings: readJson<Finding[]>(path.join(caseRoot, 'expected/findings.json')),
                },
                expectedSummary: fs.readFileSync(path.join(caseRoot, 'expected/findings.txt'), 'utf8'),
            };
        });
}

function runCase(fixture: FixtureCase, tools: PublicApiTools): CaseOutput {
    const before = tools.generateSnapshot(fixture.beforeRoot, { majorFlags: fixture.majorFlags });
    const after = tools.generateSnapshot(fixture.afterRoot, { majorFlags: fixture.majorFlags });

    return {
        before: before.files,
        after: after.files,
        findings: tools.classifyChanges(before, after, { headVersion: fixture.headVersion }),
    };
}

function once<Result>(compute: () => Result): () => Result {
    let result: { value: Result } | undefined;

    return () => {
        result ??= { value: compute() };

        return result.value;
    };
}

function unitKindParts(kind: UnitKind): { label: string; part: UnitKind }[] {
    return (Object.keys(PART_LABELS) as (keyof UnitKind)[]).flatMap((field) =>
        kind[field].map((value) => ({ label: PART_LABELS[field](value), part: { ...NO_PARTS, [field]: [value] } })),
    );
}

function isEmpty({ before, after, findings }: CaseOutput): boolean {
    return Object.keys({ ...before, ...after }).length === 0 && findings.length === 0;
}

/**
 * Parts of a unit kind that occur in no expected output. Such a part would compare two empty slices and pass,
 * for example after a typo in `UNIT_KINDS` or a change of a file format or of the element ids.
 */
function unitKindProblems(cases: FixtureCase[]): string[] {
    return Object.entries(UNIT_KINDS).flatMap(([name, kind]) =>
        unitKindParts(kind)
            .filter(({ part }) => cases.every(({ expected }) => isEmpty(sliceCase(expected, part))))
            .map(({ label }) => `No fixture case has expected ${label} for the unit kind ${name}.`),
    );
}

function problem(message: string, entries: readonly string[]): string[] {
    return entries.length > 0 ? [`${message}: ${entries.join(', ')}.`] : [];
}

/**
 * Entries of the expected-fail list that no longer mean anything.
 */
function expectedFailureProblems(expectedFailures: ExpectedFailures, tests: FixtureTest[]): string[] {
    const comparisons = expectedFailures.comparisons.map((name) => ({
        name,
        test: tests.find((test) => test.name === name),
    }));

    return [
        ...problem(
            'The expected-fail list names views that do not exist',
            expectedFailures.views.filter((view) => !Object.hasOwn(VIEWS, view)),
        ),
        ...problem(
            'The expected-fail list names comparisons that do not exist',
            comparisons.filter(({ test }) => test === undefined).map(({ name }) => name),
        ),
        ...problem(
            'The expected-fail list names comparisons whose view it lists already',
            comparisons
                .filter(({ test }) => test !== undefined && expectedFailures.views.includes(test.view))
                .map(({ name }) => name),
        ),
    ];
}

/**
 * @private
 *
 * The comparisons of every case: one per unit kind and one of the complete output. A comparison on the
 * expected-fail list is marked, and the caller asserts it with `it.failing`.
 */
export function fixtureTests(
    cases: FixtureCase[],
    expectedFailures: ExpectedFailures,
    tools: PublicApiTools,
): FixtureTest[] {
    const tests = cases.flatMap((fixture) => {
        const output = once(() => runCase(fixture, tools));

        return Object.entries(VIEWS).map(([view, slice]) => {
            const name = `${fixture.name}/${view}`;

            return {
                name,
                view,
                expectedToFail: expectedFailures.views.includes(view) || expectedFailures.comparisons.includes(name),
                actual: () => slice(output()),
                expected: slice(fixture.expected),
            };
        });
    });
    const problems = [
        ...expectedFailureProblems(expectedFailures, tests),
        ...unitKindProblems(cases),
    ];

    if (problems.length > 0) {
        throw new Error(problems.join('\n'));
    }

    return tests;
}
