/**
 * @sw-package framework
 */

import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { ESLint } from 'eslint';
import { globSync } from 'glob';
import tseslint from 'typescript-eslint';

// eslint-disable-next-line @typescript-eslint/no-require-imports
const swTestRules = require('../../../eslint-rules/test-rules');

const [
    ,
    ,
    stabilizedFeatureFlag,
    targetDirectory = 'src',
] = process.argv;

if (!stabilizedFeatureFlag) {
    throw new Error('Please provide the feature flag to stabilize.');
}

const resolvedTargetDirectory = resolve(targetDirectory);
if (!existsSync(resolvedTargetDirectory)) {
    throw new Error(`Target directory does not exist: ${resolvedTargetDirectory}`);
}

async function run(): Promise<void> {
    const testFiles = globSync('**/*.spec.{js,ts}', {
        cwd: resolvedTargetDirectory,
        absolute: true,
        nodir: true,
        ignore: '**/*.spec.vue2.{js,ts}',
    });

    const eslint = new ESLint({
        fix: true,
        overrideConfigFile: true,
        overrideConfig: [
            {
                files: [
                    '**/*.js',
                    '**/*.ts',
                ],
                languageOptions: {
                    parser: tseslint.parser,
                    parserOptions: {
                        ecmaVersion: 'latest',
                        sourceType: 'module',
                    },
                },
                plugins: {
                    'sw-test-rules': swTestRules,
                },
                rules: {
                    'sw-test-rules/stabilize-feature-flag': [
                        'error',
                        stabilizedFeatureFlag,
                    ],
                },
            },
        ],
    });

    const results = await eslint.lintFiles(testFiles);
    await ESLint.outputFixes(results);

    const changedFiles = results.filter((result) => result.output !== undefined);
    changedFiles.forEach((result) => console.info(`Updated ${result.filePath}`));

    console.info(`Stabilized ${stabilizedFeatureFlag} in ${changedFiles.length} test file(s).`);
}

run().catch((error: unknown) => {
    console.error(error);
    process.exitCode = 1;
});
