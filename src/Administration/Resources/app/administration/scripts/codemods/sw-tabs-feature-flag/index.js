/**
 * @sw-package framework
 */

const { ESLint } = require('eslint');
const path = require('path');

const usage = [
    'Usage:',
    '  npm run codemod:sw-tabs-feature-flag -- [--fix] [file-or-glob ...]',
    '',
    'Defaults to "src/**/*.html.twig" and reports feature-flagged sw-tabs migration candidates.',
    'Pass --fix to write safe feature-flagged mt-tabs branches.',
].join('\n');

function parseArguments(argv) {
    const options = {
        fix: false,
        help: false,
        filePaths: [],
    };

    argv.forEach((argument) => {
        if (argument === '--fix' || argument === '-f') {
            options.fix = true;
            return;
        }

        if (argument === '--help' || argument === '-h') {
            options.help = true;
            return;
        }

        options.filePaths.push(argument);
    });

    if (options.filePaths.length === 0) {
        options.filePaths.push('src/**/*.html.twig');
    }

    return options;
}

async function main() {
    const options = parseArguments(process.argv.slice(2));

    if (options.help) {
        // eslint-disable-next-line no-console
        console.log(usage);
        return;
    }

    const twigVuePlugin = require('eslint-plugin-twig-vue');
    const vueParser = require('vue-eslint-parser');
    const deprecationRules = require(path.resolve(__dirname, '../../../eslint-rules/deprecation-rules'));

    const eslint = new ESLint({
        fix: options.fix,
        overrideConfigFile: true,
        overrideConfig: [
            {
                files: ['**/*.html.twig'],
                plugins: {
                    'twig-vue': twigVuePlugin,
                    'sw-deprecation-rules': deprecationRules,
                },
                languageOptions: {
                    parser: vueParser,
                    parserOptions: {
                        sourceType: 'module',
                    },
                },
                processor: twigVuePlugin.processors['twig-vue'],
                rules: {
                    'sw-deprecation-rules/no-deprecated-component-usage': ['error', 'migrateSwTabsFeatureFlag'],
                },
            },
        ],
    });

    const results = await eslint.lintFiles(options.filePaths);

    if (options.fix) {
        await ESLint.outputFixes(results);
    }

    const formatter = await eslint.loadFormatter('stylish');
    const resultText = formatter.format(results);

    if (resultText) {
        // eslint-disable-next-line no-console
        console.log(resultText);
    }

    const errorCount = results.reduce((count, result) => count + result.errorCount, 0);

    if (errorCount > 0) {
        process.exitCode = 1;
    }
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
