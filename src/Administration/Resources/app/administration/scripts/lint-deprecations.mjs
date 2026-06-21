import { spawnSync } from 'node:child_process';

const changedFiles = spawnSync('git', ['status', '-s'], {
    encoding: 'utf8',
});

if (changedFiles.status !== 0) {
    process.stderr.write(changedFiles.stderr);
    process.exit(changedFiles.status ?? 1);
}

const files = changedFiles.stdout
    .split('\n')
    .map((line) => {
        return {
            status: line.slice(0, 2),
            path: line.slice(3).trim(),
        };
    })
    .filter(({ status, path }) => {
        return (
            path &&
            !status.includes('D') &&
            !path.startsWith('src/app/deprecation-registry/') &&
            path !== 'src/app/plugin/deprecation.plugin.ts' &&
            /\.(?:js|ts|vue|html\.twig)$/.test(path)
        );
    })
    .map(({ path }) => path);

if (files.length === 0) {
    process.exit(0);
}

const eslint = spawnSync(
    'eslint',
    [
        '--no-warn-ignored',
        '--rule',
        '"sw-deprecation-rules/private-feature-declarations": "error"',
        ...files,
    ],
    {
        stdio: 'inherit',
    },
);

process.exit(eslint.status ?? 1);
