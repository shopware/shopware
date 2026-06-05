/**
 * @sw-package framework
 */

const { ESLint } = require('eslint');
const path = require('path');

const usage = [
    'Usage:',
    '  npm run codemod:sw-tabs-feature-flag -- [--inventory] [--fix] [file-or-glob ...]',
    '',
    'Defaults to Admin and Storefront administration Twig files and reports feature-flagged sw-tabs migration candidates.',
    'Pass --inventory to print a bucketed report without failing on migration findings.',
    'Pass --fix to write safe feature-flagged mt-tabs branches.',
].join('\n');

const shopwareRoot = path.resolve(__dirname, '../../../../../../../..');
const administrationRoot = path.resolve(__dirname, '../../..');

const defaultFilePaths = [
    'src/Administration/Resources/app/administration/src/**/*.html.twig',
    'src/Storefront/Resources/app/administration/src/**/*.html.twig',
];

const ignoredConsumerPaths = [
    'src/Administration/Resources/app/administration/src/app/component/base/sw-tabs/**',
    'src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-deprecated/**',
    'src/Administration/Resources/app/administration/src/app/component/base/sw-tabs-item/**',
];

const inventoryBuckets = [
    {
        key: 'safeStatic',
        title: 'safe static tabs',
    },
    {
        key: 'routeDriven',
        title: 'route-driven tabs',
    },
    {
        key: 'contentSlot',
        title: 'content-slot tabs',
    },
    {
        key: 'dynamicLists',
        title: 'dynamic v-for tab lists',
    },
    {
        key: 'existingItems',
        title: 'existing items props',
    },
    {
        key: 'wrapperExtension',
        title: 'wrapper or extension-aware tabs',
    },
    {
        key: 'unsupportedRisky',
        title: 'unsupported or risky markup requiring manual migration',
    },
];

const wrapperOrExtensionPathSegments = [
    '/src/Administration/Resources/app/administration/src/app/component/extension-api/sw-extension-component-section/',
    '/src/Administration/Resources/app/administration/src/app/component/meteor/sw-meteor-card/',
    '/src/Administration/Resources/app/administration/src/app/component/meteor/sw-meteor-page/',
    '/src/Storefront/Resources/app/administration/src/extension/',
];

function parseArguments(argv) {
    const options = {
        fix: false,
        help: false,
        inventory: false,
        filePaths: [],
    };

    argv.forEach((argument) => {
        if (argument === '--fix' || argument === '-f') {
            options.fix = true;
            return;
        }

        if (argument === '--inventory' || argument === '-i') {
            options.inventory = true;
            return;
        }

        if (argument === '--help' || argument === '-h') {
            options.help = true;
            return;
        }

        options.filePaths.push(normalizeFilePath(argument));
    });

    if (options.filePaths.length === 0) {
        options.filePaths.push(...defaultFilePaths);
    }

    return options;
}

function normalizeFilePath(filePath) {
    if (path.isAbsolute(filePath)) {
        return filePath;
    }

    if (filePath.startsWith('../')) {
        return path.relative(shopwareRoot, path.resolve(administrationRoot, filePath)).replace(/\\/g, '/');
    }

    if (filePath.startsWith('src/Administration/') || filePath.startsWith('src/Storefront/')) {
        return filePath;
    }

    return `src/Administration/Resources/app/administration/${filePath}`;
}

function relativeFilePath(filePath) {
    return path.relative(shopwareRoot, filePath).replace(/\\/g, '/');
}

function manualReasonsFromMessage(message) {
    const match = message.match(/Cannot automatically migrate to "mt-tabs": (?<reasons>.*)\.$/);

    if (!match?.groups?.reasons) {
        return [];
    }

    return match.groups.reasons.split('; ');
}

function bucketForReason(reason) {
    if (reason === 'route tabs need manual onClick migration') {
        return 'routeDriven';
    }

    if (reason === 'content slots need manual active-tab state migration') {
        return 'contentSlot';
    }

    if ([
        'dynamic "v-for" tab items need manual item builders',
        'dynamic tab lists need manual item builders',
    ].includes(reason)) {
        return 'dynamicLists';
    }

    if ([
        'existing "items" props need manual feature-flag migration',
        'mixed "items" prop and slot children need manual migration',
    ].includes(reason)) {
        return 'existingItems';
    }

    if (reason === 'wrapper component tab integrations need manual migration') {
        return 'wrapperExtension';
    }

    return 'unsupportedRisky';
}

function classifyMessage(message) {
    if (message === '"sw-tabs" is deprecated. Please use "mt-tabs" with the "items" property instead.') {
        return [{
            bucket: 'safeStatic',
            reason: 'safe static feature-flag candidate',
        }];
    }

    if (message === '"sw-tabs-item" is deprecated. Please define tab entries through the "items" property on "mt-tabs" instead.') {
        return [{
            bucket: 'unsupportedRisky',
            reason: 'standalone sw-tabs-item usage',
        }];
    }

    const manualReasons = manualReasonsFromMessage(message);

    if (manualReasons.length === 0) {
        return [{
            bucket: 'unsupportedRisky',
            reason: message,
        }];
    }

    return manualReasons.map((reason) => {
        return {
            bucket: bucketForReason(reason),
            reason,
        };
    });
}

function isWrapperOrExtensionPath(filePath) {
    return wrapperOrExtensionPathSegments.some((pathSegment) => filePath.includes(pathSegment.slice(1)));
}

function classifyFinding(finding) {
    const classifications = classifyMessage(finding.message);

    if (isWrapperOrExtensionPath(finding.filePath) && !classifications.some(({ bucket }) => bucket === 'wrapperExtension')) {
        classifications.push({
            bucket: 'wrapperExtension',
            reason: 'wrapper or extension-aware component file',
        });
    }

    return classifications;
}

function collectInventory(results) {
    const findings = [];
    const buckets = new Map(inventoryBuckets.map((bucket) => [
        bucket.key,
        [],
    ]));

    results.forEach((result) => {
        result.messages
            .filter((message) => message.ruleId === 'sw-deprecation-rules/no-deprecated-component-usage')
            .forEach((message) => {
                const finding = {
                    filePath: relativeFilePath(result.filePath),
                    line: message.line,
                    column: message.column,
                    message: message.message,
                };

                findings.push(finding);

                classifyFinding(finding).forEach((classification) => {
                    buckets.get(classification.bucket).push({
                        ...finding,
                        reason: classification.reason,
                    });
                });
            });
    });

    return {
        findings,
        buckets,
    };
}

function formatInventory(results) {
    const { findings, buckets } = collectInventory(results);
    const filesWithFindings = new Set(findings.map((finding) => finding.filePath));
    const lines = [
        'sw-tabs feature-flag migration inventory',
        '',
        `Files checked: ${results.length}`,
        `Files with findings: ${filesWithFindings.size}`,
        `sw-tabs findings: ${findings.length}`,
        'A single finding can appear in multiple buckets when it has multiple migration risks.',
    ];

    inventoryBuckets.forEach((bucket) => {
        const bucketFindings = buckets.get(bucket.key);

        lines.push('');
        lines.push(`${bucket.title} (${bucketFindings.length})`);

        if (bucketFindings.length === 0) {
            lines.push('  - none');
            return;
        }

        bucketFindings.forEach((finding) => {
            lines.push(`  - ${finding.filePath}:${finding.line}:${finding.column} - ${finding.reason}`);
        });
    });

    return lines.join('\n');
}

async function main() {
    const options = parseArguments(process.argv.slice(2));

    if (options.help) {
        // eslint-disable-next-line no-console
        console.log(usage);
        return;
    }

    if (options.fix && options.inventory) {
        console.error('The --inventory option cannot be combined with --fix.');
        process.exit(1);
    }

    const twigVuePlugin = require('eslint-plugin-twig-vue');
    const vueParser = require('vue-eslint-parser');
    const deprecationRules = require(path.resolve(__dirname, '../../../eslint-rules/deprecation-rules'));

    const eslint = new ESLint({
        cwd: shopwareRoot,
        fix: options.fix,
        overrideConfigFile: true,
        overrideConfig: [
            {
                ignores: ignoredConsumerPaths,
            },
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
                    'sw-deprecation-rules/no-deprecated-component-usage': [
                        'error',
                        'migrateSwTabsFeatureFlag',
                        'onlySwTabs',
                    ],
                },
            },
        ],
    });

    const results = await eslint.lintFiles(options.filePaths);

    if (options.fix) {
        await ESLint.outputFixes(results);
    }

    if (options.inventory) {
        // eslint-disable-next-line no-console
        console.log(formatInventory(results));

        const fatalErrorCount = results.reduce((count, result) => {
            return count + result.messages.filter((message) => message.fatal).length;
        }, 0);

        if (fatalErrorCount > 0) {
            process.exitCode = 1;
        }

        return;
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
