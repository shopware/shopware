/**
 * @sw-package framework
 *
 * Shared execution primitives the check runner builds on: bounded concurrency
 * (pool + semaphore), the reproduction-command formatter, target→config
 * grouping, and the generic baseline verdict. Tool-specific runners and the
 * orchestrator compose these; nothing here knows about a specific tool.
 */

import path from 'path';
import { canonicalizePath } from './shared';
import type { AdministrationTarget } from './shared';
import type { BaselineSplit } from './baseline';
import type { Limiter, ToolRunResult, ToolStatus } from './check-types';

/** Minimal bounded-parallelism pool: runs all jobs with at most `limit` in flight. */
export async function runPool<T>(jobs: Array<() => Promise<T>>, limit: number): Promise<T[]> {
    const results: T[] = new Array<T>(jobs.length);
    let nextIndex = 0;

    async function worker(): Promise<void> {
        while (nextIndex < jobs.length) {
            const jobIndex = nextIndex;

            nextIndex += 1;
            results[jobIndex] = await jobs[jobIndex]();
        }
    }

    await Promise.all(Array.from({ length: Math.max(1, Math.min(limit, jobs.length)) }, () => worker()));

    return results;
}

/**
 * Counting semaphore shared across every child-process fan-out of one check
 * run: at most `capacity` limited jobs execute concurrently, FIFO. The
 * per-extension pool alone cannot bound a single extension's internal fan-out.
 */
export function createLimiter(capacity: number): Limiter {
    const limit = Math.max(1, capacity);
    let active = 0;
    const waiting: Array<() => void> = [];

    return async <T>(job: () => Promise<T>): Promise<T> => {
        if (active < limit) {
            active += 1;
        } else {
            await new Promise<void>((resolve) => waiting.push(resolve));
        }

        try {
            return await job();
        } finally {
            // Hand the slot to the next waiter directly — decrementing first
            // would let a fresh caller and the woken waiter both claim it.
            const next = waiting.shift();

            if (next) {
                next();
            } else {
                active -= 1;
            }
        }
    };
}

export function formatCommand(cwd: string, args: string[]): string {
    const quote = (value: string): string => (/\s/.test(value) ? JSON.stringify(value) : value);

    return `cd ${quote(cwd)} && ${quote(process.execPath)} ${args.map(quote).join(' ')}`;
}

export interface TargetProgramGroup {
    configPath: string;
    targets: AdministrationTarget[];
}

/** Groups targets by their canonical config path so an identical config is executed once. */
export function groupTargetsByConfig(
    projectRoot: string,
    targets: AdministrationTarget[],
    configOf: (target: AdministrationTarget) => string,
): TargetProgramGroup[] {
    const groups = new Map<string, TargetProgramGroup>();

    for (const target of targets) {
        const configPath = path.resolve(projectRoot, configOf(target));
        const key = canonicalizePath(configPath);
        const group = groups.get(key) ?? { configPath: key, targets: [] };

        group.targets.push(target);
        groups.set(key, group);
    }

    return [...groups.values()].sort((left, right) => left.configPath.localeCompare(right.configPath));
}

/**
 * Turns a completed tool run and its baseline split into the reported status
 * and the new/baselined/stale counts. A clean run passes; a run whose findings
 * are all baselined also passes (its output is then suppressed like any pass);
 * a non-zero exit we cannot attribute to baselined findings stays failed —
 * including the parse-mismatch case, so a parser bug never greens real findings.
 */
export function applyBaseline<F>(
    runStatus: number,
    totalFindings: number,
    split: BaselineSplit<F>,
    refOf: (finding: F) => { file: string; code: string },
): Pick<
    ToolRunResult,
    'status' | 'findings' | 'newFindings' | 'baselinedFindings' | 'staleBaseline' | 'newFindingRefs' | 'baselinedFindingRefs'
> {
    const newFindings = split.newFindings.length;
    let status: ToolStatus;

    if (runStatus === 0) {
        status = 'passed';
    } else if (newFindings > 0 || split.parseMismatch || totalFindings === 0) {
        status = 'failed';
    } else {
        // Non-zero exit, but every reported finding matched the baseline.
        status = 'passed';
    }

    return {
        status,
        findings: totalFindings,
        newFindings,
        baselinedFindings: split.baselinedFindings.length,
        staleBaseline: split.staleCount,
        newFindingRefs: split.newFindings.map(refOf),
        baselinedFindingRefs: split.baselinedFindings.map(refOf),
    };
}
