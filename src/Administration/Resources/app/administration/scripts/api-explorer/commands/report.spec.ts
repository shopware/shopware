/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { component, node, prop, root, snapshot } from '../diff/create-diff.spec/builders';
import type { ApiDiff } from '../diff/types';
import type { CommentData } from '../report/comment-data';
import type { ReportSummary } from '../report/types';
import type { ApiSnapshot } from '../snapshot/types';
import { runCli } from '../cli';
import { createReportCommand } from './report';
import { UsageError } from './types';

const HEAD_SHA = '3333333ccccccccccccccccccccccccccccccccc';

const SNAPSHOTS: Record<string, ApiSnapshot> = {
    'v6.7.0.0': snapshot({
        ref: 'v6.7.0.0',
        commit: '1111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        components: [
            component('sw-foo', { props: [prop('value')] }),
            component('sw-bar'),
            component('sw-secret', { visibility: 'internal', props: [prop('value')] }),
        ],
        shopwareObject: root([node('Shopware.Component')]),
    }),
    compatible: snapshot({
        ref: 'compatible',
        commit: '2222222bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        components: [
            component('sw-foo', { props: [prop('value'), prop('size')] }),
            component('sw-bar'),
            component('sw-secret', { visibility: 'internal', props: [prop('value')] }),
        ],
        shopwareObject: root([node('Shopware.Component')]),
    }),
    WORKTREE: snapshot({
        ref: 'WORKTREE',
        commit: null,
        components: [component('sw-foo'), component('sw-secret', { visibility: 'internal' })],
        shopwareObject: root([node('Shopware.Component'), node('Shopware.Utils')]),
        diagnostics: [{ file: null, message: 'Something was skipped.' }],
    }),
};

describe('api-explorer report command', () => {
    let outDir: string;
    let requested: string[];
    let stdout: string;

    function command() {
        return createReportCommand({
            createService: () =>
                Promise.resolve({
                    getSnapshot: (ref: string) => {
                        requested.push(ref);

                        return SNAPSHOTS[ref]
                            ? Promise.resolve(SNAPSHOTS[ref])
                            : Promise.reject(new Error(`Unknown ref ${ref}`));
                    },
                }),
            resolveWorktreeCommit: () => Promise.resolve(HEAD_SHA),
            buildApp: (dir) => {
                fs.writeFileSync(path.join(dir, 'app.js'), '(function(){})();');
                fs.writeFileSync(path.join(dir, 'app.css'), '');

                return Promise.resolve();
            },
            now: () => new Date('2026-10-21T08:00:00.000Z'),
        });
    }

    function run(...args: string[]): Promise<number> {
        return command().run(args, {
            stdout: (text) => {
                stdout += text;
            },
            stderr: () => undefined,
        });
    }

    const read = (file: string): string => fs.readFileSync(path.join(outDir, file), 'utf8');

    beforeEach(() => {
        outDir = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-report-')), 'nested', 'out');
        requested = [];
        stdout = '';
    });

    afterEach(() => {
        fs.rmSync(path.dirname(path.dirname(outDir)), { recursive: true, force: true });
    });

    it('compares the base with the working tree by default and writes every file', async () => {
        const exitCode = await run('--base', 'v6.7.0.0', '--out-dir', outDir);

        expect(exitCode).toBe(0);
        expect(requested.sort()).toEqual([
            'WORKTREE',
            'v6.7.0.0',
        ]);
        expect(fs.readdirSync(outDir).sort()).toEqual([
            'app.css',
            'app.js',
            'comment-data.json',
            'comment.md',
            'data.js',
            'diff.json',
            'index.html',
            'summary.json',
        ]);
        expect(stdout).toContain(path.join(outDir, 'index.html'));

        const diff = JSON.parse(read('diff.json')) as ApiDiff;
        expect(diff.base.ref).toBe('v6.7.0.0');
        expect(diff.summary.hasBreaking).toBe(true);

        expect(read('data.js')).toContain('"generatedAt":"2026-10-21T08:00:00.000Z"');
        expect(read('comment.md')).toMatch(/^<!-- admin-api-explorer-report -->\n/);
        expect(read('comment.md')).toContain('`WORKTREE` (checkout of `3333333`)');
        expect(read('comment.md')).not.toContain('full report](');

        const commentData = JSON.parse(read('comment-data.json')) as CommentData;
        expect(commentData.base).toEqual({ ref: 'v6.7.0.0', commit: '1111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' });
        expect(commentData.head).toEqual({ ref: 'WORKTREE', commit: HEAD_SHA });
        expect(commentData.counts).toEqual(diff.summary.bySeverity);
    });

    it('writes the summary with the extraction counts of both sides', async () => {
        await run('--base', 'v6.7.0.0', '--out-dir', outDir);

        const summary = JSON.parse(read('summary.json')) as ReportSummary;
        const diff = JSON.parse(read('diff.json')) as ApiDiff;

        expect(summary).toEqual({
            total: diff.summary.total,
            bySeverity: diff.summary.bySeverity,
            privateBySeverity: diff.summary.privateBySeverity,
            hasBreaking: true,
            base: {
                ref: 'v6.7.0.0',
                commit: '1111111aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                components: 3,
                objectNodes: 1,
                diagnostics: 0,
            },
            head: { ref: 'WORKTREE', commit: HEAD_SHA, components: 2, objectNodes: 2, diagnostics: 1 },
        });
    });

    it('rates private changes by their impact without failing the check', async () => {
        const exitCode = await run('--base', 'v6.7.0.0', '--out-dir', outDir, '--fail-on-breaking');
        const summary = JSON.parse(read('summary.json')) as ReportSummary;
        const commentData = JSON.parse(read('comment-data.json')) as CommentData;

        expect(exitCode).toBe(3);
        expect(summary.bySeverity.private).toBe(1);
        expect(summary.privateBySeverity.breaking).toBe(1);
        expect(summary.bySeverity.breaking).toBe(2);
        expect(commentData.version).toBe(2);
        expect(commentData.privateCounts).toEqual(summary.privateBySeverity);
        expect(read('comment.md')).toContain(
            '**Private API** (not a public contract, never turns this check red): 1 breaking',
        );
    });

    it('records the checked-out HEAD as the commit of a WORKTREE head', async () => {
        await run('--base', 'v6.7.0.0', '--head', 'WORKTREE', '--out-dir', outDir);

        expect((JSON.parse(read('summary.json')) as ReportSummary).head.commit).toBe(HEAD_SHA);
        expect((JSON.parse(read('comment-data.json')) as CommentData).head.commit).toBe(HEAD_SHA);
        expect(read('data.js')).toContain(`"worktreeCommit":"${HEAD_SHA}"`);
        expect(read('index.html')).toContain('<title>Admin API Explorer: v6.7.0.0 vs. Checked-out commit @ 3333333</title>');
    });

    it('exits with 3 for breaking changes with --fail-on-breaking and still writes everything', async () => {
        const exitCode = await run(
            '--base',
            'v6.7.0.0',
            '--out-dir',
            outDir,
            '--fail-on-breaking',
            '--report-url',
            'https://github.com/shopware/shopware/actions/runs/1',
        );

        expect(exitCode).toBe(3);
        expect(fs.existsSync(path.join(outDir, 'index.html'))).toBe(true);
        expect(read('comment.md')).toContain(
            '[Download the full report](https://github.com/shopware/shopware/actions/runs/1)',
        );
    });

    it('exits with 0 with --fail-on-breaking when nothing breaks', async () => {
        const exitCode = await run('--base', 'v6.7.0.0', '--head', 'compatible', '--out-dir', outDir, '--fail-on-breaking');

        expect(exitCode).toBe(0);
        expect(requested.sort()).toEqual([
            'compatible',
            'v6.7.0.0',
        ]);
        expect(read('comment.md')).toContain('`compatible` @ `2222222` (head)');
        expect((JSON.parse(read('summary.json')) as ReportSummary).hasBreaking).toBe(false);
    });

    it.each([
        [[]],
        [['--out-dir', 'out']],
        [['--base', 'trunk']],
        [
            [
                '--base',
                '',
                '--out-dir',
                'out',
            ],
        ],
        [
            [
                '--base',
                'trunk',
                '--out-dir',
                'out',
                '--head',
                '',
            ],
        ],
        [
            [
                '--base',
                'trunk',
                '--out-dir',
                'out',
                '--report-url',
                'javascript:alert(1)',
            ],
        ],
        [
            [
                '--base',
                'trunk',
                '--out-dir',
                'out',
                '--report-url',
                'not a url',
            ],
        ],
        [
            [
                '--base',
                'trunk',
                '--out-dir',
                'out',
                'extra',
            ],
        ],
        [
            [
                '--base',
                'trunk',
                '--out-dir',
                'out',
                '--unknown',
            ],
        ],
    ])('exits with 2 and prints the usage for invalid arguments %j', async (args) => {
        let stderr = '';
        const exitCode = await runCli(['report', ...args], {
            stdout: () => undefined,
            stderr: (text) => {
                stderr += text;
            },
        });

        expect(exitCode).toBe(2);
        expect(stderr).toContain('Usage: api-explorer report');
    });

    it('fails with a runtime error when a ref cannot be read', async () => {
        await expect(run('--base', 'missing', '--out-dir', outDir)).rejects.not.toBeInstanceOf(UsageError);
        expect(fs.existsSync(path.join(outDir, 'comment.md'))).toBe(false);
    });
});
