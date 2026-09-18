/**
 * Rewrites `await wrapper.setData({...})` to direct `wrapper.vm` assignments across the spec suite.
 *
 * `sw-test-rules/no-set-data` reports every call and fixes what it can, but it is a warning in the
 * repository config while the suite still holds calls it cannot rewrite. This runs the same fixer as an
 * error over the spec tree, and reports what is left for a hand rewrite.
 *
 * Usage, from src/Administration/Resources/app/administration:
 *
 *     npm run codemod:set-data-to-vm                          # report what would change
 *     npm run codemod:set-data-to-vm -- --write               # apply it
 *     npm run codemod:set-data-to-vm -- --write src/sw-order  # or to one subtree
 */
import { ESLint } from 'eslint';
import path from 'path';
import tsParser from '@typescript-eslint/parser';

/* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, global-require */
const noSetDataRule = require('../../../eslint-rules/test-rules/no-set-data');

const administrationRoot = path.resolve(__dirname, '../../..');
const write = process.argv.includes('--write');
const target = process.argv.slice(2).find((argument) => !argument.startsWith('--')) ?? 'src';

function createESLint(fix: boolean): ESLint {
    return new ESLint({
        cwd: administrationRoot,
        // The repository config is deliberately not loaded: this runs one rule over files it would
        // otherwise leave alone, and every other rule's opinion is noise in the report.
        overrideConfigFile: true,
        overrideConfig: [
            {
                files: [
                    '**/*.spec.js',
                    '**/*.spec.ts',
                ],
                languageOptions: { parser: tsParser, ecmaVersion: 2023, sourceType: 'module' },
                // Every `eslint-disable` in the suite points at a rule this run does not enable, so
                // ESLint would call them all unused and strip them as part of `--fix`.
                linterOptions: { reportUnusedDisableDirectives: 'off' },
                plugins: { 'sw-test-rules': { rules: { 'no-set-data': noSetDataRule } } },
                rules: { 'sw-test-rules/no-set-data': 'error' },
            },
        ],
        fix,
    });
}

async function run(): Promise<void> {
    const patterns = [
        path.join(target, '**/*.spec.js'),
        path.join(target, '**/*.spec.ts'),
    ];

    // Counted before anything is written: a fixing run reports only what it could not fix.
    const reported = await createESLint(false).lintFiles(patterns);

    let rewritten = 0;
    const byHand: string[] = [];

    for (const result of reported) {
        for (const message of result.messages) {
            if (message.messageId === 'silentNoOp') {
                rewritten += 1;
            } else if (message.messageId === 'silentNoOpManualRewrite') {
                byHand.push(
                    `${path.relative(administrationRoot, result.filePath)}:${message.line}  ` +
                        `${message.message.split('because ').pop() ?? ''}`,
                );
            }
        }
    }

    if (write) {
        await ESLint.outputFixes(await createESLint(true).lintFiles(patterns));
    }

    /* eslint-disable-next-line no-console */
    console.log(
        [
            `${write ? 'rewrote' : 'would rewrite'} ${rewritten} setData calls`,
            `${byHand.length} calls need a hand rewrite:`,
            ...byHand.map((entry) => `  ${entry}`),
            '',
            write ? 'Run `npm run format:fix`, then the suite, before committing.' : 'Re-run with --write to apply.',
        ].join('\n'),
    );
}

run().catch((error: unknown) => {
    /* eslint-disable-next-line no-console */
    console.error(error);
    process.exit(1);
});
