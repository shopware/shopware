import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { getLintableFiles } = require('./changed-files');

const adminRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

/**
 * Exits with the command's status when it failed, or with 1 and the reason when it could not be
 * spawned at all, so a missing binary never fails silently. Returns when the command succeeded.
 *
 * @example
 * exitOnFailure('git', spawnSync('git', ['status'])); // returns, git exited with 0
 */
function exitOnFailure(command, result) {
    if (result.error) {
        console.error(`Could not run ${command}: ${result.error.message}`);
        process.exit(1);
    }

    if (result.status !== 0) {
        process.stderr.write(result.stderr ?? '');
        process.exit(result.status ?? 1);
    }
}

function runGit(args) {
    const result = spawnSync('git', args, {
        cwd: adminRoot,
        encoding: 'utf8',
    });

    exitOnFailure('git', result);

    return result.stdout;
}

const prefix = runGit(['rev-parse', '--show-prefix']).trim();
const files = getLintableFiles(
    runGit([
        'status',
        '--porcelain',
        '--untracked-files=all',
        '-z',
        '--',
        '.',
    ]),
    prefix,
);

if (files.length === 0) {
    process.exit(0);
}

const eslint = spawnSync(
    path.join(adminRoot, 'node_modules/.bin/eslint'),
    [
        '--no-warn-ignored',
        '--quiet',
        '--rule',
        '"sw-deprecation-rules/private-feature-declarations": "error"',
        ...files,
    ],
    {
        cwd: adminRoot,
        stdio: 'inherit',
    },
);

exitOnFailure('ESLint', eslint);
