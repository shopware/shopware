/**
 * @sw-package framework
 *
 * `npm run mutation:changed [-- --base <ref>] [-- --list] [-- <stryker args>]`
 *
 * Runs StrykerJS only on the files changed against `trunk` (or `--base`), so a feature branch
 * or an agent's working tree gets mutation feedback in minutes instead of the hours a full
 * `src/core` run takes. Everything else about the run comes from `stryker.config.mjs`; the
 * `--mutate` list built here replaces the `mutate` scope of that config.
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { collectChangedFiles, resolveMutationTargets } from './changed-files';
import type { ResolverFs } from './changed-files';

export interface CliOptions {
    baseRef: string;
    listOnly: boolean;
    strykerArgs: string[];
}

export function parseArgs(argv: string[]): CliOptions {
    const options: CliOptions = { baseRef: 'trunk', listOnly: false, strykerArgs: [] };

    for (let i = 0; i < argv.length; i += 1) {
        const arg = argv[i];

        if (arg === '--base') {
            options.baseRef = argv[i + 1] ?? options.baseRef;
            i += 1;
        } else if (arg.startsWith('--base=')) {
            options.baseRef = arg.slice('--base='.length);
        } else if (arg === '--list') {
            options.listOnly = true;
        } else {
            options.strykerArgs.push(arg);
        }
    }

    return options;
}

/**
 * Diff runs keep their own incremental file, otherwise a preceding full `npm run mutation` would
 * make every changed-files report list all of `src/core` again.
 */
export const CHANGED_INCREMENTAL_FILE = 'build/artifacts/stryker/incremental-changed.json';

export function buildStrykerArgs(options: CliOptions, targets: string[]): string[] {
    const args = [
        'run',
        ...options.strykerArgs,
    ];

    if (!options.strykerArgs.some((arg) => arg === '--incrementalFile' || arg.startsWith('--incrementalFile='))) {
        args.push('--incrementalFile', CHANGED_INCREMENTAL_FILE);
    }

    return [
        ...args,
        '--mutate',
        targets.join(','),
    ];
}

function createNodeFs(root: string): ResolverFs {
    return {
        exists: (file) => fs.existsSync(path.join(root, file)),
        readFile: (file) => fs.readFileSync(path.join(root, file), 'utf8'),
    };
}

function main(): number {
    const root = path.resolve(__dirname, '../..');
    const options = parseArgs(process.argv.slice(2));
    const changed = collectChangedFiles(root, options.baseRef);
    const { targets, skipped } = resolveMutationTargets(changed, createNodeFs(root));

    for (const entry of skipped) {
        console.info(`skip ${entry.file}: ${entry.reason}`);
    }

    if (targets.length === 0) {
        console.info(`No mutation targets: no changed JS/TS files under src/ against ${options.baseRef}.`);

        return 0;
    }

    console.info(`Mutating ${targets.length} file(s) changed against ${options.baseRef}:`);
    targets.forEach((target) => console.info(`  ${target}`));

    if (options.listOnly) {
        return 0;
    }

    const stryker = path.join(root, 'node_modules', '.bin', 'stryker');
    const result = spawnSync(stryker, buildStrykerArgs(options, targets), {
        cwd: root,
        stdio: 'inherit',
    });

    return result.status ?? 1;
}

if (require.main === module) {
    process.exitCode = main();
}
