const { ESLint } = require('eslint');
const process = require('process');
const commandLineArgs = require('command-line-args');
const getUsage = require('command-line-usage');
const fs = require('fs-extra');
const colors = require('picocolors');
const path = require('path');
const { globSync } = require('glob');

// Available Shopware versions
const shopwareVersions = [
    '6.6',
    '6.7',
];

// Command options
const optionDefinitions = [
    {
        description: 'Prints this help page.',
        name: 'help',
        alias: 'h',
        type: Boolean,
    },
    {
        description: 'Plugin folder name inside custom/plugins!',
        name: 'plugin-name',
        alias: 'p',
        type: String,
    },
    {
        description: 'Shopware root. Default ../../../../../',
        name: 'shopware-root',
        alias: 'r',
        type: String,
    },
    {
        description: 'Source files to fix. Default "src/Resources/app/administration/src"',
        name: 'src',
        alias: 's',
        multiple: true,
        type: String,
    },
    {
        description: 'Runs the automatic code fixer.',
        name: 'fix',
        alias: 'f',
        type: Boolean,
    },
    {
        description: 'Ignores that the folder is not a git repository.',
        name: 'ignore-git',
        alias: 'G',
        type: Boolean,
    },
    {
        // eslint-disable-next-line max-len
        description: `Define the Shopware version for loading the correct codemods. Available: ${shopwareVersions.join(', ')}`,
        name: 'shopware-version',
        alias: 'v',
        type: String,
    },
];

// Command help
const sections = [
    {
        header: 'Shopware Admin code mods',
        content: 'Run shopware code mods in your plugin!',
    },
    {
        header: 'Synopsis',
        content: [
            '{bold Run as npm script inside <shopwareRoot>/src/Administration/Resources/app/administration}:',
            '$ npm run code-mods -- [{bold --fix}] {bold --plugin-name} {underline SwagExamplePlugin}',
            '$ npm run code-mods -- {bold --help}',
            '',
            '{bold Run as composer script inside <shopwareRoot>}:',
            '$ composer run admin:code-mods -- [{bold --fix}] {bold --plugin-name} {underline SwagExamplePlugin}',
            '$ composer run admin:code-mods -- {bold --help}',
        ],
    },
    {
        header: 'Options',
        optionList: optionDefinitions,
    },
];

// Use IIFE to be able to await
(async function codeMods() {
    // Parse options into object
    const options = commandLineArgs(optionDefinitions);
    if (options.help || Object.keys(options).length === 0) {
        // eslint-disable-next-line no-console
        console.log(getUsage(sections));
        process.exit();
    }

    const shopwareVersion = options['shopware-version'];
    if (shopwareVersion && !shopwareVersions.includes(shopwareVersion)) {
        console.error(colors.red('Invalid Shopware version. Available: 6.6, 6.7'));
        process.exit(1);
    }

    const pluginName = options['plugin-name'];
    if (!pluginName) {
        console.error(colors.red('You have to specify your plugin name.'));
        process.exit(1);
    }

    let customPluginsPath = path.resolve('../../../../../custom/plugins');
    if (options['shopware-root']) {
        let optionsRoot = options['shopware-root'];

        // remove trailing slash
        optionsRoot = optionsRoot.replace(/\/$/, '');

        customPluginsPath = path.resolve(`${optionsRoot}/custom/plugins`);
    }

    // Looking for all the possible plugins inside the plugin folder using a path pattern
    const pluginDir = `${customPluginsPath}/${pluginName}`;
    const ADMIN_PLUGIN_PATH_PATTERN = `${pluginDir}/src/**/Resources/app/administration/src/`;
    const srcDirectories = options.src || globSync(ADMIN_PLUGIN_PATH_PATTERN, { nodir: false });

    if (!srcDirectories.length) {
        // eslint-disable-next-line no-console
        console.error(colors.red(`Unable to locate "${pluginName}" in "${customPluginsPath}"!`));
        // eslint-disable-next-line no-console
        console.log(colors.red('You need to specify at least one src folder or use the default!'));
        process.exit(1);
    }

    let pluginIsGitRepository = false;
    try {
        pluginIsGitRepository = fs.statSync(`${pluginDir}/.git`).isDirectory();
    } catch (e) {
        pluginIsGitRepository = false;
    }

    if (!pluginIsGitRepository && !options['ignore-git']) {
        console.error(
            colors.red('Plugin is no git repository. Make sure your plugin is in git and has a clean work space!'),
        );
        console.error(colors.red('If you feel adventurous you can ignore this with -G... You where warned!'));
        process.exit(1);
    }

    createPluginsTsConfigFile(pluginName);

    let index = 1;
    // eslint-disable-next-line no-restricted-syntax
    for (const srcDir of srcDirectories) {
        // eslint-disable-next-line no-plusplus
        console.info(colors.green(`(${index++}/${srcDirectories.length}) Checking ${srcDir}...`));

        const workingDir = `./${pluginName}`;
        if (fs.existsSync(workingDir)) {
            fs.rmSync(workingDir, { recursive: true, force: true });
        }

        // copy plugin into admin folder to make eslint work correctly
        copyFolderRecursiveSync(srcDir, workingDir);

        const fix = options.fix;
        try {
            await lintFiles([workingDir], fix, shopwareVersion);

            // only copy back changes if fix is requested
            if (fix) {
                // copy back changes and delete working dir
                copyFolderRecursiveSync(workingDir, srcDir);
            }
        } catch (e) {
            console.error(colors.red(`Linting failed! ${srcDir}`));
            console.error(e);
        } finally {
            // always remove the working dir, because src files could collide
            fs.rmSync(workingDir, { recursive: true, force: true });
        }
    }

    removePluginsTsConfigFile();
})();

// Helper functions
function copyFolderRecursiveSync(source, target) {
    // don't copy .git folder - permission problems
    const excludeFolders = [
        '.git',
        'node_modules',
        'vendor',
    ]; // Folders to exclude

    // Ensure target directory exists
    fs.ensureDirSync(target);

    fs.copySync(source, target, {
        filter: (src) => {
            const relativePath = path.relative(source, src);
            return !excludeFolders.some((folder) => relativePath.includes(folder));
        },
    });
}

// Create an instance of ESLint with the configuration passed to the function
function createESLintInstance(overrideConfig, fix) {
    return new ESLint({
        overrideConfig,
        fix,
        extensions: [
            '.html.twig',
            '.js',
            '.ts',
        ],
    });
}

// Lint the specified files and return the results
async function lintAndFix(eslint, filePaths, shopwareVersion) {
    const results = await eslint.lintFiles(filePaths, shopwareVersion);

    // Apply automatic fixes and output fixed code
    await ESLint.outputFixes(results);

    return results;
}

// Log results to console if there are any problems
async function outputLintingResults(results, eslint) {
    // 4. Format the results.
    const formatter = await eslint.loadFormatter('stylish');
    const resultText = formatter.format(results);

    // eslint-disable-next-line no-console
    console.log(resultText);
}

// Put previous functions all together
async function lintFiles(filePaths, fix, shopwareVersion) {
    // The ESLint configuration. Alternatively, you could load the configuration
    // from a .eslintrc file or just use the default config.
    const overrideConfig = {
        plugins: [
            'twig-vue',
            'sw-core-rules',
            'sw-deprecation-rules',
        ],
        overrides: [
            {
                extends: [
                    'plugin:vue/base', // we need to use the plugin to load jsx support
                ],
                processor: 'twig-vue/twig-vue',
                files: ['**/*.html.twig'],
                rules: {
                    ...(() => {
                        if (isVersionNewerOrSame(shopwareVersion, '6.7')) {
                            return {
                                'sw-deprecation-rules/no-deprecated-components': ['error'],
                                'sw-deprecation-rules/no-deprecated-component-usage': ['error'],
                            };
                        }

                        return {
                            'sw-deprecation-rules/no-deprecated-components': 'off',
                            'sw-deprecation-rules/no-deprecated-component-usage': 'off',
                        };
                    })(),
                    // Disabled rules
                    'eol-last': 'off',
                    'no-multiple-empty-lines': 'off',
                    'vue/comment-directive': 'off',
                    'vue/jsx-uses-vars': 'off',
                    'max-len': 'off',
                    'comma-dangle': 'off',
                },
            },
            {
                extends: [
                    'plugin:vue/vue3-recommended',
                    '@shopware-ag/eslint-config-base',
                ],
                files: ['**/*.js'],
                excludedFiles: [
                    '*.spec.js',
                    '*.spec.vue3.js',
                ],
                rules: {
                    'vue/no-deprecated-destroyed-lifecycle': 'error',
                    'vue/no-deprecated-events-api': 'error',
                    'vue/require-slots-as-functions': 'error',
                    'vue/no-deprecated-props-default-this': 'error',
                    'sw-deprecation-rules/no-compat-conditions': ['error'],
                    'sw-deprecation-rules/no-empty-listeners': ['error'],
                    'sw-core-rules/require-explicit-emits': 'error',
                    'sw-core-rules/require-package-annotation': 'off',
                    'sw-deprecation-rules/private-feature-declarations': 'off',
                    'comma-dangle': 'off',
                },
            },
            {
                files: [
                    '**/*.ts',
                    '**/*.tsx',
                ],
                extends: [
                    'plugin:vue/vue3-recommended',
                    '@shopware-ag/eslint-config-base',
                ],
                parser: '@typescript-eslint/parser',
                parserOptions: {
                    tsconfigRootDir: __dirname,
                    project: ['./tsconfig-plugins.json'],
                },
                plugins: ['@typescript-eslint'],
                rules: {
                    'sw-deprecation-rules/no-compat-conditions': ['error'],
                    'sw-deprecation-rules/no-empty-listeners': ['error'],
                    'sw-core-rules/require-explicit-emits': 'error',
                    'sw-core-rules/require-package-annotation': 'off',
                    'sw-deprecation-rules/private-feature-declarations': 'off',
                    'comma-dangle': 'off',
                },
            },
        ],
    };

    const eslint = createESLintInstance(overrideConfig, fix);
    const results = await lintAndFix(eslint, filePaths, shopwareVersion);
    return outputLintingResults(results, eslint);
}

function createPluginsTsConfigFile(pluginName) {
    const tsConfig = {
        extends: './tsconfig.json',
        include: [
            `./${pluginName}/**/*`,
        ],
    };

    fs.writeFileSync('./tsconfig-plugins.json', JSON.stringify(tsConfig));
}

function removePluginsTsConfigFile() {
    fs.rmSync('./tsconfig-plugins.json', { force: true });
}

function isVersionNewerOrSame(version, compareVersion) {
    const versionParts = version.split('.');
    const compareVersionParts = compareVersion.split('.');

    for (let i = 0; i < versionParts.length; i++) {
        const versionPart = parseInt(versionParts[i], 10);
        const compareVersionPart = parseInt(compareVersionParts[i], 10);

        if (versionPart > compareVersionPart) {
            return true;
        }

        if (versionPart < compareVersionPart) {
            return false;
        }
    }

    return true;
}
