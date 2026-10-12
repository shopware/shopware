import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { getLintableFiles } = require('./lint-deprecations-files');

function runGit(args) {
    const result = spawnSync('git', args, {
        encoding: 'utf8',
    });

    if (result.status !== 0) {
        process.stderr.write(result.stderr);
        process.exit(result.status ?? 1);
    }

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
    'eslint',
    [
        '--no-warn-ignored',
        '--quiet',
        '--rule',
        '"sw-deprecation-rules/private-feature-declarations": "error"',
        ...files,
    ],
    {
        stdio: 'inherit',
    },
);

process.exit(eslint.status ?? 1);
