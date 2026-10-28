/**
 * Shared grader helpers for the sw-review evals.
 *
 * Lives at tests/_lib.ts. skillgrade mounts it into each task's workspace as ./_lib.ts
 * next to the task's grader.ts, which imports it as `./_lib.ts`. The import is a sibling
 * path on purpose: a grader runs in that workspace, never from the repository layout.
 *
 * Pattern:
 *
 *   import { findings, grade, majorOrBlocking, rule } from './_lib.ts';
 *
 *   grade((g, out) => {
 *       g.checkSchemaPersona();
 *       g.check('persona-attribution', out.persona === 'architecture');
 *       g.require('mig-rule-finding', findings(out).some(rule('MIG-')));
 *       g.check('mig-major-or-blocking', findings(out).some(all(rule('MIG-'), majorOrBlocking)));
 *   });
 *
 * `grade` reads OUT (default output.json), runs the predicates and prints exactly one
 * line: {"score": 0..1, "details": "...", "checks": [{name, passed, message}]}.
 * A missing or unparsable output file scores 0 without running the predicates; so does
 * a grader that throws.
 *
 * Needs Node 22.18 or newer: the graders run with `node grader.ts` (type stripping,
 * no build step, no dependencies).
 */

import { readFileSync } from 'node:fs';

// Field values from references/SCHEMA.md.
export type Severity = 'blocking' | 'major' | 'minor' | 'nit';
export type Category =
    | 'security'
    | 'correctness'
    | 'tests'
    | 'maintainability'
    | 'performance'
    | 'compatibility'
    | 'docs'
    | 'supply_chain'
    | 'privacy'
    | 'scope';
export type Decision = 'comment' | 'request_changes' | 'block' | 'needs_human_review';
export type RiskLevel = 'low' | 'medium' | 'high' | 'critical';
export type Persona = 'security' | 'architecture' | 'code-style' | 'ux' | 'maintainer';

export const DECISIONS: readonly Decision[] = ['comment', 'request_changes', 'block', 'needs_human_review'];
export const RISK_LEVELS: readonly RiskLevel[] = ['low', 'medium', 'high', 'critical'];

/**
 * One finding of a per-persona or merged review. Every field is optional because the
 * grader checks what the model produced, not what the schema promises.
 */
export type Finding = {
    severity?: Severity;
    category?: Category;
    file?: string;
    line?: number;
    claim?: string;
    evidence?: string;
    impact?: string;
    suggested_fix?: string;
    confidence?: number;
    requires_human?: boolean;
    rule_id?: string;
    // merged review only
    persona?: Persona;
    concurring_personas?: Persona[];
    related_rule_ids?: string[];
};

/** The per-persona and the merged review shape of SCHEMA.md, as one type. */
export type Output = {
    schema_version?: string;
    persona?: Persona | string;
    summary?: string;
    risk_level?: RiskLevel | string;
    decision?: Decision | string;
    guides_read?: string[];
    findings?: Finding[];
    // merged review only
    pr?: { number?: number | null; head_sha?: string };
    personas_run?: string[];
    personas_skipped?: Array<{ persona: string; reason: string }>;
    guides_applied?: string[];
    not_reviewed?: Array<{ paths: string[]; changed_lines: number; reason: string }>;
    requires_human?: boolean;
    persona_summaries?: Record<string, string>;
};

export type Check = { name: string; passed: boolean; message: string };
export type Result = { score: number; details: string; checks: Check[] };

export type Predicate = (finding: Finding) => boolean;

/** `(.findings // [])`: the findings array, or an empty one when it is missing or not an array. */
export function findings(out: Output): Finding[] {
    return Array.isArray(out.findings) ? out.findings : [];
}

/** rule_id starts with `prefix`: a guide prefix (`'MIG-'`) or one exact id (`'DOCS-003'`). */
export function rule(prefix: string): Predicate {
    return (finding) => (finding.rule_id ?? '').startsWith(prefix);
}

/** rule_id matches `pattern`, for findings that may come from one of several guides. */
export function ruleMatches(pattern: RegExp): Predicate {
    return (finding) => pattern.test(finding.rule_id ?? '');
}

/** rule_id is one of `ids` exactly. */
export function ruleIn(ids: readonly string[]): Predicate {
    return (finding) => ids.includes(finding.rule_id ?? '');
}

export function severity(...levels: readonly Severity[]): Predicate {
    return (finding) => finding.severity !== undefined && levels.includes(finding.severity);
}

export const blocking: Predicate = severity('blocking');
export const majorOrBlocking: Predicate = severity('major', 'blocking');
export const atLeastMinor: Predicate = severity('minor', 'major', 'blocking');

export function category(...categories: readonly Category[]): Predicate {
    return (finding) => finding.category !== undefined && categories.includes(finding.category);
}

/** `file` is one of `paths` exactly (repo-relative). */
export function file(...paths: readonly string[]): Predicate {
    return (finding) => finding.file !== undefined && paths.includes(finding.file);
}

export const requiresHuman: Predicate = (finding) => finding.requires_human === true;

/** Every predicate holds for the finding. */
export function all(...predicates: readonly Predicate[]): Predicate {
    return (finding) => predicates.every((predicate) => predicate(finding));
}

/** Any predicate holds for the finding. */
export function any(...predicates: readonly Predicate[]): Predicate {
    return (finding) => predicates.some((predicate) => predicate(finding));
}

export function not(predicate: Predicate): Predicate {
    return (finding) => !predicate(finding);
}

export function personasRunIncludes(out: Output, slug: string): boolean {
    return (out.personas_run ?? []).includes(slug);
}

export function personasRunExcludes(out: Output, slug: string): boolean {
    return !personasRunIncludes(out, slug);
}

export function personasSkippedIncludes(out: Output, slug: string): boolean {
    return (out.personas_skipped ?? []).some((entry) => entry.persona === slug);
}

export class Grader {
    readonly out: Output;

    private checks: Check[] = [];

    private passed = 0;

    private requiredFailed = false;

    constructor(out: Output) {
        this.out = out;
    }

    /** Registers one predicate. Returns whether it passed. */
    check(name: string, passed: boolean): boolean {
        this.checks.push({
            name,
            passed,
            message: passed ? 'ok' : `predicate failed: ${name}`,
        });
        if (passed) {
            this.passed += 1;
        }

        return passed;
    }

    /**
     * Like check, but a failed predicate zeroes the whole score. Use it for the planted
     * expectation of a case (the finding a catch case must produce, the finding an ignore
     * case must not produce), so a run cannot pass on schema checks alone.
     */
    require(name: string, passed: boolean): boolean {
        if (!this.check(name, passed)) {
            this.requiredFailed = true;
        }

        return passed;
    }

    checkSchemaPersona(): void {
        const out = this.out;
        this.check('schema-version-1', out.schema_version === '1');
        this.check('persona-field', 'persona' in out);
        this.check('decision-valid', DECISIONS.includes(out.decision as Decision));
        this.check('risk-level-valid', RISK_LEVELS.includes(out.risk_level as RiskLevel));
        this.check('findings-is-array', out.findings === undefined || Array.isArray(out.findings));
    }

    checkSchemaMerged(): void {
        const out = this.out;
        this.check('schema-version-1', out.schema_version === '1');
        this.check('decision-valid', DECISIONS.includes(out.decision as Decision));
        this.check('risk-level-valid', RISK_LEVELS.includes(out.risk_level as RiskLevel));
        this.check('findings-is-array', out.findings === undefined || Array.isArray(out.findings));
        this.check('personas-run-is-array', out.personas_run === undefined || Array.isArray(out.personas_run));
    }

    result(): Result {
        const total = this.checks.length;
        const score = total === 0 || this.requiredFailed ? 0 : Math.round((this.passed / total) * 100) / 100;

        return {
            score,
            details: `${this.passed}/${total} checks passed`,
            checks: [...this.checks],
        };
    }
}

function failure(name: string, details: string, message: string): Result {
    return { score: 0, details, checks: [{ name, passed: false, message }] };
}

/** Reads the review output. A missing or invalid file yields a zero-score result instead. */
export function loadOutput(path: string): { out: Output } | { failure: Result } {
    let raw: string;
    try {
        raw = readFileSync(path, 'utf8');
    } catch {
        return {
            failure: failure(
                'output-exists',
                `${path} missing — agent did not produce expected output file`,
                `${path} not found in workspace`,
            ),
        };
    }

    try {
        const parsed: unknown = JSON.parse(raw);
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return { failure: failure('output-parses', `${path} is not a JSON object`, 'top-level value is not an object') };
        }

        return { out: parsed as Output };
    } catch (error) {
        return { failure: failure('output-parses', `${path} is not valid JSON`, (error as Error).message) };
    }
}

/**
 * Runs one grader: loads OUT (default output.json), lets `define` register the checks and
 * prints the result as one JSON line. Never throws; a throwing grader scores 0.
 */
export function grade(define: (grader: Grader, out: Output) => void, path: string = process.env.OUT ?? 'output.json'): Result {
    const loaded = loadOutput(path);
    let result: Result;

    if ('failure' in loaded) {
        result = loaded.failure;
    } else {
        const grader = new Grader(loaded.out);
        try {
            define(grader, loaded.out);
            result = grader.result();
        } catch (error) {
            result = failure('grader-runs', 'grader threw', (error as Error).message);
        }
    }

    process.stdout.write(`${JSON.stringify(result)}\n`);

    return result;
}
