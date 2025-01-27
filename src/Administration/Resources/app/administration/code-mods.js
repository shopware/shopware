const { ESLint } = require('eslint');
const process = require('process');
const commandLineArgs = require('command-line-args');
const getUsage = require('command-line-usage');
const fs = require('fs');
const chalk = require('chalk');
const path = require('path');

// Available Shopware versions
const shopwareVersions = ['6.6', '6.7'];

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
(async function () {
    // Parse options into object
    const options = commandLineArgs(optionDefinitions);
    if (options.help || Object.keys(options).length === 0) {
        // eslint-disable-next-line no-console
        console.log(getUsage(sections));
        process.exit();
    }

    const shopwareVersion = options['shopware-version'];
    if (shopwareVersion && !shopwareVersions.includes(shopwareVersion)) {
        console.error(chalk.red('Invalid Shopware version. Available: 6.6, 6.7'));
        process.exit(1);
    }

    const pluginName = options['plugin-name'];
    if (!pluginName) {
        console.error(chalk.red('You have to specify your plugin name.'));
        process.exit(1);
    }

    let customPluginsPath = path.resolve('../../../../../custom/plugins');
    if (options['shopware-root']) {
        let optionsRoot = options['shopware-root'];

        // remove trailing slash
        optionsRoot = optionsRoot.replace(/\/$/, '');

        customPluginsPath = path.resolve(`${optionsRoot}/custom/plugins`);
    }

    const src = options.src || ['src/Resources/app/administration/src'];
    if (!src.length) {
        // eslint-disable-next-line no-console
        console.log(chalk.red('You need to specify at least one src folder or use the default!'));
        process.exit();
    }

    let pluginFound = false;
    const pluginDir = `${customPluginsPath}/${pluginName}`;
    const pluginAdminSrcDir = `${customPluginsPath}/${pluginName}/src/Resources/app/administration/src`;
    try {
        pluginFound = fs.statSync(pluginAdminSrcDir).isDirectory();
    } catch (e) {
        pluginFound = false;
    }

    if (!pluginFound) {
        console.error(chalk.red(`Unable to locate "${pluginName}" in "${customPluginsPath}"!`));
        process.exit(1);
    }

    let pluginIsGitRepository = false;
    try {
        pluginIsGitRepository = fs.statSync(`${pluginDir}/.git`).isDirectory();
    } catch (e) {
        pluginIsGitRepository = false;
    }

    if (!pluginIsGitRepository && !options['ignore-git']) {
        console.error(chalk.red('Plugin is no git repository. Make sure your plugin is in git and has a clean work space!'));
        console.error(chalk.red('If you feel adventurous you can ignore this with -G... You where warned!'));
        process.exit(1);
    }

    createPluginsTsConfigFile(pluginName);

    src.forEach(async (folder) => {
        let srcFound = false;
        const pluginFolder = `${customPluginsPath}/${pluginName}`;
        const srcDir = `${customPluginsPath}/${pluginName}/${folder}`;
        try {
            srcFound = fs.statSync(srcDir).isDirectory();
        } catch (e) {
            srcFound = false;
        }

        if (!srcFound) {
            console.error(chalk.red(`Unable to locate folder "${folder}" in "${pluginFolder}"!`));
            process.exit(1);
        }

        const workingDir = `./${pluginName}`;
        if (fs.existsSync(workingDir)) {
            fs.rmSync(workingDir, { recursive: true, force: true });
        }

        // copy plugin into admin folder to make eslint work correctly
        copyFolderRecursiveSync(pluginAdminSrcDir, workingDir);

        const fix = options.fix;
        await lintFiles([workingDir], fix, shopwareVersion);

        // only copy back changes if fix is requested
        if (fix) {
            // copy back changes and delete working dir
            copyFolderRecursiveSync(workingDir, pluginAdminSrcDir);
        }

        // always remove the working dir, because src files could collide
        fs.rmSync(workingDir, { recursive: true, force: true });
    });

    removePluginsTsConfigFile();
}());

// Helper functions
function copyFolderRecursiveSync(source, target) {
    // don't copy .git folder - permission problems
    if (target.includes('.git') || target.includes('node_modules') || target.includes('vendor')) {
        return;
    }

    // Ensure target directory exists
    if (!fs.existsSync(target)) {
        fs.mkdirSync(target);
    }

    // Get list of files and subdirectories in source directory
    const files = fs.readdirSync(source);

    files.forEach(file => {
        const sourcePath = path.join(source, file);
        const targetPath = path.join(target, file);

        if (fs.lstatSync(sourcePath).isDirectory()) {
            // Recursively copy subdirectories
            copyFolderRecursiveSync(sourcePath, targetPath);
        } else {
            // Copy file
            fs.copyFileSync(sourcePath, targetPath);
        }
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
                },
            },
            {
                extends: [
                    'plugin:vue/vue3-recommended',
                    '@shopware-ag/eslint-config-base',
                ],
                files: ['**/*.js'],
                excludedFiles: ['*.spec.js', '*.spec.vue3.js'],
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
                },
            },
            {
                files: ['**/*.ts', '**/*.tsx'],
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
