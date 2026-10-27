/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { parseArgs } from 'node:util';
import { createDiff } from '../diff/create-diff';
import { findRepoRoot, resolveRef } from '../git';
import { createCommentData } from '../report/comment-data';
import { renderPrComment, REPORT_ARTIFACT_NAME } from '../report/markdown';
import { writeStaticSite, type StaticAppBuilder } from '../report/static-site';
import { createReportSummary } from '../report/summary';
import type { StaticReportData } from '../report/types';
import { createSnapshotService, type SnapshotService } from '../service/snapshot-service';
import { WORKTREE_REF } from '../snapshot/types';
import { EXIT_BREAKING } from './diff';
import { EXIT_OK, UsageError, type CliCommand } from './types';

const ADMIN_ROOT = path.resolve(__dirname, '..', '..', '..');

export interface ReportDependencies {
    createService: () => Promise<Pick<SnapshotService, 'getSnapshot'>>;
    /** Commit of the checkout, shown next to the working tree in the comment. */
    resolveWorktreeCommit: () => Promise<string | null>;
    buildApp?: StaticAppBuilder;
    now?: () => Date;
}

const defaultDependencies: ReportDependencies = {
    createService: async () => createSnapshotService({ repoRoot: await findRepoRoot(ADMIN_ROOT), adminRoot: ADMIN_ROOT }),
    resolveWorktreeCommit: async () => resolveRef(await findRepoRoot(ADMIN_ROOT), 'HEAD').catch(() => null),
};

function parseReportUrl(value: string | undefined): string | null {
    if (value === undefined) {
        return null;
    }

    try {
        const url = new URL(value);

        if (url.protocol === 'https:' || url.protocol === 'http:') {
            return url.href;
        }
    } catch {
        // Reported below.
    }

    throw new UsageError(`--report-url must be an http(s) URL, got "${value}"`);
}

function writeJson(file: string, value: unknown): void {
    fs.writeFileSync(file, `${JSON.stringify(value, null, 4)}\n`);
}

export function createReportCommand(dependencies: ReportDependencies = defaultDependencies): CliCommand {
    return {
        summary: 'Compare two refs and write a static HTML report, a PR comment and JSON results.',
        usage: [
            'report --base <ref> [--head <ref>] --out-dir <dir> [--report-url <url>] [--fail-on-breaking]',
            '',
            '  --base              Git ref to compare against, e.g. trunk',
            `  --head              Git ref or ${WORKTREE_REF} (default) for the checkout`,
            '  --out-dir           Directory for index.html, comment.md, comment-data.json, diff.json and summary.json',
            `  --report-url        Link to the "${REPORT_ARTIFACT_NAME}" artifact for the comment`,
            `  --fail-on-breaking  Exit with ${EXIT_BREAKING} when the diff contains a breaking change`,
        ].join('\n'),

        async run(args, io) {
            const { values, positionals } = parseArgs({
                args,
                options: {
                    base: { type: 'string' },
                    head: { type: 'string' },
                    'out-dir': { type: 'string' },
                    'report-url': { type: 'string' },
                    'fail-on-breaking': { type: 'boolean' },
                },
                strict: true,
                allowPositionals: true,
            });

            if (positionals.length > 0) {
                throw new UsageError(`Unexpected argument: ${positionals[0]}`);
            }

            for (const [
                name,
                value,
            ] of Object.entries(values)) {
                if (value === '') {
                    throw new UsageError(`--${name} must not be empty`);
                }
            }

            const { base, 'out-dir': outDirOption } = values;
            const head = values.head ?? WORKTREE_REF;

            if (base === undefined || outDirOption === undefined) {
                throw new UsageError(`--${base === undefined ? 'base' : 'out-dir'} is required`);
            }

            const reportUrl = parseReportUrl(values['report-url']);
            const outDir = path.resolve(outDirOption);
            const service = await dependencies.createService();
            const [
                baseSnapshot,
                headSnapshot,
            ] = await Promise.all([
                service.getSnapshot(base),
                service.getSnapshot(head),
            ]);
            const diff = createDiff(baseSnapshot, headSnapshot);
            const worktreeCommit = head === WORKTREE_REF ? await dependencies.resolveWorktreeCommit() : null;
            const data: StaticReportData = {
                base: baseSnapshot,
                head: headSnapshot,
                diff,
                worktreeCommit,
                generatedAt: (dependencies.now?.() ?? new Date()).toISOString(),
            };

            fs.mkdirSync(outDir, { recursive: true });
            writeJson(path.join(outDir, 'diff.json'), diff);
            writeJson(
                path.join(outDir, 'summary.json'),
                createReportSummary(baseSnapshot, headSnapshot, diff, worktreeCommit),
            );
            writeJson(path.join(outDir, 'comment-data.json'), createCommentData(diff, worktreeCommit));
            fs.writeFileSync(path.join(outDir, 'comment.md'), renderPrComment(diff, { reportUrl, worktreeCommit }));
            await writeStaticSite(outDir, data, dependencies.buildApp);

            const { summary } = diff;
            io.stdout(
                `${summary.total} changes, ${summary.bySeverity.breaking} breaking. Report written to ${path.join(outDir, 'index.html')}\n`,
            );

            return values['fail-on-breaking'] && summary.hasBreaking ? EXIT_BREAKING : EXIT_OK;
        },
    };
}

export const reportCommand = createReportCommand();
