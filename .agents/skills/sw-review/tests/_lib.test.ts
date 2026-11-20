/**
 * Unit tests for the grader helpers in tests/_lib.ts, plus one run of every grader.
 *
 *   node --test tests/_lib.test.ts
 *
 * The grader run copies grader.ts and _lib.ts side by side into a temp dir, the same
 * shape skillgrade mounts into a task workspace, and grades an empty review: an ignore
 * case must score 1 on it and a catch case must score 0.
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join, relative } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    type Finding,
    type Output,
    type Result,
    Grader,
    all,
    any,
    atLeastMinor,
    blocking,
    category,
    file,
    findings,
    grade,
    majorOrBlocking,
    not,
    personasRunExcludes,
    personasRunIncludes,
    personasSkippedIncludes,
    requiresHuman,
    rule,
    ruleIn,
    ruleMatches,
    severity,
} from './_lib.ts';

const HERE = fileURLToPath(new URL('.', import.meta.url));
const LIB = join(HERE, '_lib.ts');

const validPersona: Output = {
    schema_version: '1',
    persona: 'ux',
    decision: 'comment',
    risk_level: 'low',
    findings: [],
};

const validMerged: Output = {
    schema_version: '1',
    decision: 'request_changes',
    risk_level: 'high',
    findings: [],
    personas_run: ['security', 'ux'],
};

const invalid = {
    schema_version: '2',
    decision: 'approve',
    risk_level: 'none',
    findings: 'none',
    personas_run: 'security',
} as unknown as Output;

const names = (result: Result): string[] => result.checks.map((check) => check.name);
const outcomes = (result: Result): boolean[] => result.checks.map((check) => check.passed);

function tempDir(): string {
    return mkdtempSync(join(tmpdir(), 'sw-review-grader-'));
}

/** Runs `grade` with stdout captured, so its result line does not end up in the test output. */
function gradeQuietly(define: Parameters<typeof grade>[0], path: string): { result: Result; stdout: string } {
    const write = process.stdout.write;
    let stdout = '';
    process.stdout.write = ((chunk: string | Uint8Array): boolean => {
        stdout += String(chunk);

        return true;
    }) as typeof process.stdout.write;
    try {
        return { result: grade(define, path), stdout };
    } finally {
        process.stdout.write = write;
    }
}

test('findings returns the array, or an empty one when it is missing or not an array', () => {
    const list: Finding[] = [{ rule_id: 'MIG-001' }];
    assert.deepEqual(findings({ findings: list }), list);
    assert.deepEqual(findings({}), []);
    assert.deepEqual(findings({ findings: 'none' } as unknown as Output), []);
    assert.deepEqual(findings({ findings: null } as unknown as Output), []);
});

test('rule matches a rule_id prefix or an exact id and treats a missing id as empty', () => {
    assert.equal(rule('MIG-')({ rule_id: 'MIG-002' }), true);
    assert.equal(rule('MIG-')({ rule_id: 'DIX-001' }), false);
    assert.equal(rule('MIG-')({}), false);
    assert.equal(rule('DOCS-003')({ rule_id: 'DOCS-003' }), true);
    assert.equal(rule('DOCS-003')({ rule_id: 'DOCS-004' }), false);
});

test('ruleMatches tests the rule_id against a pattern', () => {
    const scopeOrCache = ruleMatches(/^(SCOPE|CACHE)-/);
    assert.equal(scopeOrCache({ rule_id: 'SCOPE-001' }), true);
    assert.equal(scopeOrCache({ rule_id: 'CACHE-002' }), true);
    assert.equal(scopeOrCache({ rule_id: 'X-SCOPE-001' }), false);
    assert.equal(scopeOrCache({}), false);
});

test('ruleIn matches exact ids only', () => {
    const planted = ruleIn(['BCPHP-001', 'BCPHP-006']);
    assert.equal(planted({ rule_id: 'BCPHP-001' }), true);
    assert.equal(planted({ rule_id: 'BCPHP-007' }), false);
    assert.equal(planted({ rule_id: 'BCPHP-0010' }), false);
    assert.equal(planted({}), false);
});

test('severity and its shortcuts match the listed levels only', () => {
    assert.equal(severity('major')({ severity: 'major' }), true);
    assert.equal(severity('major')({ severity: 'blocking' }), false);
    assert.equal(severity('major')({}), false);

    assert.equal(blocking({ severity: 'blocking' }), true);
    assert.equal(blocking({ severity: 'major' }), false);

    assert.equal(majorOrBlocking({ severity: 'blocking' }), true);
    assert.equal(majorOrBlocking({ severity: 'major' }), true);
    assert.equal(majorOrBlocking({ severity: 'minor' }), false);

    assert.equal(atLeastMinor({ severity: 'minor' }), true);
    assert.equal(atLeastMinor({ severity: 'nit' }), false);
    assert.equal(atLeastMinor({}), false);
});

test('category matches any of the listed categories', () => {
    assert.equal(category('scope')({ category: 'scope' }), true);
    assert.equal(category('performance', 'correctness')({ category: 'correctness' }), true);
    assert.equal(category('performance', 'correctness')({ category: 'docs' }), false);
    assert.equal(category('scope')({}), false);
});

test('file matches exact repo-relative paths', () => {
    const changed = file('src/A.php', 'src/B.php');
    assert.equal(changed({ file: 'src/B.php' }), true);
    assert.equal(changed({ file: 'src/A.php.bak' }), false);
    assert.equal(changed({}), false);
});

test('all, any and not combine predicates', () => {
    const majorMigration = all(rule('MIG-'), majorOrBlocking);
    assert.equal(majorMigration({ rule_id: 'MIG-001', severity: 'major' }), true);
    assert.equal(majorMigration({ rule_id: 'MIG-001', severity: 'minor' }), false);
    assert.equal(majorMigration({ rule_id: 'DIX-001', severity: 'major' }), false);
    assert.equal(all()({}), true);

    const scope = any(category('scope'), rule('SCOPE-'));
    assert.equal(scope({ category: 'scope' }), true);
    assert.equal(scope({ rule_id: 'SCOPE-002' }), true);
    assert.equal(scope({ category: 'docs', rule_id: 'DOCS-001' }), false);
    assert.equal(any()({}), false);

    assert.equal(not(blocking)({ severity: 'major' }), true);
    assert.equal(not(blocking)({ severity: 'blocking' }), false);
});

test('requiresHuman is true only for requires_human === true', () => {
    assert.equal(requiresHuman({ requires_human: true }), true);
    assert.equal(requiresHuman({ requires_human: false }), false);
    assert.equal(requiresHuman({}), false);
    // An empty selection counts as "every finding requires a human", like the former Bash graders.
    assert.equal([].every(requiresHuman), true);
});

test('personas helpers read personas_run and personas_skipped', () => {
    const out: Output = {
        personas_run: ['security', 'ux'],
        personas_skipped: [{ persona: 'code-style', reason: 'no code' }],
    };
    assert.equal(personasRunIncludes(out, 'ux'), true);
    assert.equal(personasRunIncludes(out, 'maintainer'), false);
    assert.equal(personasRunExcludes(out, 'maintainer'), true);
    assert.equal(personasRunExcludes(out, 'security'), false);
    assert.equal(personasSkippedIncludes(out, 'code-style'), true);
    assert.equal(personasSkippedIncludes(out, 'ux'), false);

    assert.equal(personasRunIncludes({}, 'ux'), false);
    assert.equal(personasRunExcludes({}, 'ux'), true);
    assert.equal(personasSkippedIncludes({}, 'ux'), false);
});

test('Grader.check counts passed checks and lists every check', () => {
    const grader = new Grader(validPersona);
    assert.equal(grader.check('a', true), true);
    assert.equal(grader.check('b', false), false);

    const result = grader.result();
    assert.equal(result.score, 0.5);
    assert.equal(result.details, '1/2 checks passed');
    assert.deepEqual(result.checks, [
        { name: 'a', passed: true, message: 'ok' },
        { name: 'b', passed: false, message: 'predicate failed: b' },
    ]);
});

test('Grader.require zeroes the score and keeps the failing check listed', () => {
    const grader = new Grader(validPersona);
    grader.check('a', true);
    grader.check('b', true);
    assert.equal(grader.require('planted', false), false);

    const result = grader.result();
    assert.equal(result.score, 0);
    assert.equal(result.details, '2/3 checks passed');
    assert.deepEqual(result.checks[2], { name: 'planted', passed: false, message: 'predicate failed: planted' });

    const passing = new Grader(validPersona);
    passing.check('a', false);
    assert.equal(passing.require('planted', true), true);
    assert.equal(passing.result().score, 0.5);
});

test('Grader.checkSchemaPersona names its checks and passes on a valid output only', () => {
    const valid = new Grader(validPersona);
    valid.checkSchemaPersona();
    const validResult = valid.result();
    assert.deepEqual(names(validResult), [
        'schema-version-1',
        'persona-field',
        'decision-valid',
        'risk-level-valid',
        'findings-is-array',
    ]);
    assert.deepEqual(outcomes(validResult), [true, true, true, true, true]);
    assert.equal(validResult.score, 1);

    const broken = new Grader(invalid);
    broken.checkSchemaPersona();
    assert.deepEqual(outcomes(broken.result()), [false, false, false, false, false]);

    // A missing findings key is valid: findings() treats it as an empty list.
    const noFindings = new Grader({ ...validPersona, findings: undefined });
    noFindings.checkSchemaPersona();
    assert.equal(noFindings.result().score, 1);
});

test('Grader.checkSchemaMerged names its checks and passes on a valid output only', () => {
    const valid = new Grader(validMerged);
    valid.checkSchemaMerged();
    const validResult = valid.result();
    assert.deepEqual(names(validResult), [
        'schema-version-1',
        'decision-valid',
        'risk-level-valid',
        'findings-is-array',
        'personas-run-is-array',
    ]);
    assert.deepEqual(outcomes(validResult), [true, true, true, true, true]);

    const broken = new Grader(invalid);
    broken.checkSchemaMerged();
    assert.deepEqual(outcomes(broken.result()), [false, false, false, false, false]);
});

test('Grader.result rounds the score to two decimals and scores zero checks as 0', () => {
    const grader = new Grader(validPersona);
    grader.check('a', true);
    grader.check('b', true);
    grader.check('c', false);
    assert.equal(grader.result().score, 0.67);

    const third = new Grader(validPersona);
    third.check('a', true);
    third.check('b', false);
    third.check('c', false);
    assert.equal(third.result().score, 0.33);

    const empty = new Grader(validPersona).result();
    assert.equal(empty.score, 0);
    assert.equal(empty.details, '0/0 checks passed');
    assert.deepEqual(empty.checks, []);
});

test('grade scores a missing output file 0 with check output-exists', () => {
    const dir = tempDir();
    try {
        let ran = false;
        const { result, stdout } = gradeQuietly(() => {
            ran = true;
        }, join(dir, 'output.json'));

        assert.equal(ran, false);
        assert.equal(result.score, 0);
        assert.deepEqual(names(result), ['output-exists']);
        assert.deepEqual(JSON.parse(stdout), result);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('grade scores invalid JSON and a non-object 0 with check output-parses', () => {
    const dir = tempDir();
    try {
        for (const content of ['{"schema_version": ', '[]', 'null', '"text"']) {
            const path = join(dir, 'output.json');
            writeFileSync(path, content);
            const { result } = gradeQuietly(() => {
                throw new Error('must not run');
            }, path);

            assert.equal(result.score, 0, content);
            assert.deepEqual(names(result), ['output-parses'], content);
            assert.equal(result.checks[0].passed, false, content);
        }
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('grade scores a throwing grader 0 with check grader-runs', () => {
    const dir = tempDir();
    try {
        const path = join(dir, 'output.json');
        writeFileSync(path, JSON.stringify(validPersona));
        const { result } = gradeQuietly((g) => {
            g.checkSchemaPersona();
            throw new Error('boom');
        }, path);

        assert.equal(result.score, 0);
        assert.deepEqual(result.checks, [{ name: 'grader-runs', passed: false, message: 'boom' }]);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('grade passes the parsed output to the grader and prints one result line', () => {
    const dir = tempDir();
    try {
        const path = join(dir, 'output.json');
        writeFileSync(path, JSON.stringify(validPersona));
        const { result, stdout } = gradeQuietly((g, out) => {
            g.checkSchemaPersona();
            g.check('persona-attribution', out.persona === 'ux');
        }, path);

        assert.equal(result.score, 1);
        assert.equal(result.details, '6/6 checks passed');
        assert.equal(stdout.split('\n').length, 2);
        assert.equal(stdout.endsWith('\n'), true);
        assert.deepEqual(JSON.parse(stdout), result);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

function findGraders(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true })
        .flatMap((entry) => {
            const path = join(dir, entry.name);
            if (entry.isDirectory()) {
                return findGraders(path);
            }

            return entry.name === 'grader.ts' ? [path] : [];
        })
        .sort();
}

const graders = ['guide', 'persona'].flatMap((bucket) => findGraders(join(HERE, bucket)));

test('every grader scores an empty review as an ignore case passes and a catch case fails', async (t) => {
    assert.ok(graders.length > 0, `no graders found under ${HERE}`);

    for (const grader of graders) {
        await t.test(relative(HERE, grader), () => {
            const caseDir = dirname(grader);
            const input = JSON.parse(readFileSync(join(caseDir, 'input.json'), 'utf8')) as { persona?: unknown };
            assert.equal(typeof input.persona, 'string', 'input.json has no persona');

            const workspace = tempDir();
            try {
                copyFileSync(grader, join(workspace, 'grader.ts'));
                copyFileSync(LIB, join(workspace, '_lib.ts'));
                const output = join(workspace, 'review.json');
                writeFileSync(output, JSON.stringify({
                    schema_version: '1',
                    persona: input.persona,
                    decision: 'comment',
                    risk_level: 'low',
                    findings: [],
                }));

                const run = spawnSync(process.execPath, ['grader.ts'], {
                    cwd: workspace,
                    env: { ...process.env, OUT: output },
                    encoding: 'utf8',
                });
                assert.equal(run.status, 0, run.stderr);

                const result = JSON.parse(run.stdout) as Result;
                assert.equal(typeof result.score, 'number');
                assert.ok(result.score >= 0 && result.score <= 1, `score ${result.score} out of range`);
                assert.equal(typeof result.details, 'string');
                assert.ok(Array.isArray(result.checks));
                for (const check of result.checks) {
                    assert.equal(typeof check.name, 'string');
                    assert.equal(typeof check.passed, 'boolean');
                    assert.equal(typeof check.message, 'string');
                }
                assert.ok(!names(result).includes('grader-runs'), run.stdout);

                const expected = basename(caseDir).startsWith('ignore') ? 1 : 0;
                assert.equal(result.score, expected, run.stdout);
            } finally {
                rmSync(workspace, { recursive: true, force: true });
            }
        });
    }
});
