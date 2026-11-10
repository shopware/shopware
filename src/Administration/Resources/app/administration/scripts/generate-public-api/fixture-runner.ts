/**
 * @sw-package framework
 *
 * Compares what the generator and the break check produce for a fixture case with the case's `expected/`
 * folder. The expected files hold every unit kind, but the generator is built one kind at a time, so each
 * case is compared once per unit kind and once as a whole.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { ClassifyChanges } from './break-check';
import type { GenerateSnapshot } from './snapshot';

/**
 * @private
 *
 * What one case produces: the files of both snapshots and the summary of the break check.
 */
export interface CaseOutput {
    before: Record<string, string>;
    after: Record<string, string>;
    findings: string;
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
 * One comparison of one case: `actual()` has to equal `expected`. `name` is `<case>/<unit kind>` or
 * `<case>/complete`, the form the expected-fail list uses.
 */
export interface FixtureTest {
    name: string;
    expectedToFail: boolean;
    actual: () => unknown;
    expected: unknown;
}

interface UnitKind {
    /** Sections of the outline in an `.api.md` report. */
    reportSections: readonly string[];
    /** Members of a registry entry in a `.d.ts` contract. */
    contractMembers: readonly string[];
    /** Keys of a component entry in `index.json`. */
    indexKeys: readonly string[];
    /** Element kinds of a finding, the `block` in `component:sw-card/block:sw_card_header`. */
    elementKinds: readonly string[];
}

const UNIT_KINDS: Record<string, UnitKind> = {
    blocks: {
        reportSections: [
            'blocks',
            'crossings',
        ],
        contractMembers: [
            'blocks',
            'crossings',
        ],
        indexKeys: ['blocks'],
        elementKinds: [
            'block',
            'crossing',
        ],
    },
};

// Members of a registry entry sit three levels deep: namespace, interface, entry.
const CONTRACT_MEMBER_INDENT = ' '.repeat(12);
const CONTRACT_MEMBER = new RegExp(`^${CONTRACT_MEMBER_INDENT}(\\S+): `);
const REPORT_OUTLINE = /```text\n([\s\S]*?)```/;
const FINDING_GROUP = /^([A-Z][a-z]+) \(\d+\)$/;
const FINDING_LINE = /^ {2}\S/;
const FINDING_DETAIL_LINE = /^ {4}\S/;
const FINDING_ELEMENT_KIND = /^ {2}\S+ {2}[^/\s]+\/([a-z-]+):/;

function reportSections(report: string, sections: readonly string[]): string {
    const [
        ,
        outline = '',
    ] = REPORT_OUTLINE.exec(report) ?? [];

    return outline
        .split('\n\n')
        .filter((section) => sections.includes(section.trimStart().split(/\s/, 1)[0]))
        .join('\n\n');
}

function contractMembers(contract: string, members: readonly string[]): string {
    const lines: string[] = [];
    let closingLine: string | null = null;

    contract.split('\n').forEach((line) => {
        if (closingLine !== null) {
            lines.push(line);
            closingLine = line === closingLine ? null : closingLine;
            return;
        }

        if (!members.includes(CONTRACT_MEMBER.exec(line)?.[1] ?? '')) {
            return;
        }

        lines.push(line);

        if (!line.endsWith(';')) {
            closingLine = `${CONTRACT_MEMBER_INDENT}${line.endsWith('[') ? ']' : '}'};`;
        }
    });

    return lines.join('\n');
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

/**
 * The findings of a summary, each as its group, its line and its detail lines.
 */
function parseFindings(summary: string): string[][] {
    const findings: string[][] = [];
    let group = '';

    summary.split('\n').forEach((line) => {
        group = FINDING_GROUP.exec(line)?.[1] ?? group;

        if (FINDING_LINE.test(line)) {
            findings.push([
                group,
                line,
            ]);
        } else if (FINDING_DETAIL_LINE.test(line)) {
            findings.at(-1)?.push(line);
        }
    });

    return findings;
}

function sliceFindings(summary: string, kind: UnitKind): string[][] {
    return parseFindings(summary).filter(([, line]) =>
        kind.elementKinds.includes(FINDING_ELEMENT_KIND.exec(line)?.[1] ?? ''),
    );
}

const VIEWS: Record<string, (output: CaseOutput) => unknown> = {
    ...Object.fromEntries(
        Object.entries(UNIT_KINDS).map(([name, kind]) => [
            name,
            (output: CaseOutput) => ({
                before: sliceSnapshot(output.before, kind),
                after: sliceSnapshot(output.after, kind),
                findings: sliceFindings(output.findings, kind),
            }),
        ]),
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

/**
 * @private
 *
 * Reads every folder below `fixturesRoot` that has a `case.json`.
 */
export function loadFixtureCases(fixturesRoot: string): FixtureCase[] {
    return fs
        .readdirSync(fixturesRoot)
        .filter((name) => fs.existsSync(path.join(fixturesRoot, name, 'case.json')))
        .sort()
        .map((name) => {
            const caseRoot = path.join(fixturesRoot, name);
            const { headVersion, majorFlags } = JSON.parse(fs.readFileSync(path.join(caseRoot, 'case.json'), 'utf8')) as {
                headVersion: string;
                majorFlags: string[];
            };

            return {
                name,
                beforeRoot: path.join(caseRoot, 'before'),
                afterRoot: path.join(caseRoot, 'after'),
                headVersion,
                majorFlags,
                expected: {
                    before: readTree(path.join(caseRoot, 'expected/before')),
                    after: readTree(path.join(caseRoot, 'expected/after')),
                    findings: fs.readFileSync(path.join(caseRoot, 'expected/findings.txt'), 'utf8'),
                },
            };
        });
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

/**
 * The unit kinds whose sections, members and keys occur in no expected snapshot. Such a kind would compare
 * two empty slices and pass, for example after a typo in `UNIT_KINDS` or a change of the file format.
 */
function unitKindsWithoutExpectedOutput(cases: FixtureCase[]): string[] {
    return Object.entries(UNIT_KINDS)
        .filter(([, kind]) =>
            cases.every(({ expected }) =>
                [
                    expected.before,
                    expected.after,
                ].every((files) => Object.keys(sliceSnapshot(files, kind)).length === 0),
            ),
        )
        .map(([name]) => name);
}

/**
 * @private
 *
 * The comparisons of every case: one per unit kind and one of the complete output. A comparison named in
 * `expectedFailures` is marked, and the caller asserts it with `it.failing`. A name that matches no comparison
 * is an error, so the list cannot keep entries of a case or kind that is gone.
 */
export function fixtureTests(
    cases: FixtureCase[],
    expectedFailures: readonly string[],
    tools: PublicApiTools,
): FixtureTest[] {
    const tests = cases.flatMap((fixture) => {
        const output = once(() => runCase(fixture, tools));

        return Object.entries(VIEWS).map(([viewName, view]) => ({
            name: `${fixture.name}/${viewName}`,
            expectedToFail: expectedFailures.includes(`${fixture.name}/${viewName}`),
            actual: () => view(output()),
            expected: view(fixture.expected),
        }));
    });
    const stale = expectedFailures.filter((name) => !tests.some((test) => test.name === name));
    const unmatched = unitKindsWithoutExpectedOutput(cases);

    if (stale.length > 0) {
        throw new Error(`The expected-fail list names comparisons that do not exist: ${stale.join(', ')}`);
    }

    if (unmatched.length > 0) {
        throw new Error(`No fixture case has expected output for the unit kind: ${unmatched.join(', ')}`);
    }

    return tests;
}
