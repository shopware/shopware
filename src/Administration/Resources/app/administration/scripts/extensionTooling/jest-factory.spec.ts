/**
 * @sw-package framework
 *
 * Guards the Jest factory contract: every harness path it hands to Jest
 * exists, an extension's options extend the harness instead of replacing it,
 * an option outside the allowlist is rejected, and a missing generated file
 * fails with the command that generates it. The factory is an .mjs module
 * Jest cannot import directly, so a node subprocess builds each config and
 * serializes it.
 */

import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { pathToFileURL } from 'url';
import type { Config } from 'jest';
import { cleanupTempProject, createTempProject, realAdministrationRoot, writeFile } from './test-helpers';

const factoryPath = path.join(realAdministrationRoot, 'extension-tooling', 'jest.mjs');

/**
 * Builds a config with the factory at `factoryFile` in a fresh node process,
 * so the factory's environment writes stay out of this test process.
 */
function buildConfig(options: Record<string, unknown>, factoryFile = factoryPath): Config {
    const probeScript = [
        `const { shopwareAdminJest } = await import(${JSON.stringify(pathToFileURL(factoryFile).href)});`,
        `process.stdout.write(JSON.stringify(shopwareAdminJest(${JSON.stringify(options)})));`,
    ].join('\n');

    return JSON.parse(
        execFileSync(process.execPath, ['--input-type=module', '-e', probeScript], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }),
    ) as Config;
}

/**
 * Runs `statements` against the factory module, bound as `factory`, in a fresh node process
 * and returns the value of `resultExpression`.
 *
 * @example
 * evaluateInFactory('', 'factory.HOST_TRANSFORM_IGNORE_PATTERNS'); // → ['/node_modules/(?!(…)/)']
 */
function evaluateInFactory(statements: string, resultExpression: string): unknown {
    const probeScript = [
        `const factory = await import(${JSON.stringify(pathToFileURL(factoryPath).href)});`,
        statements,
        `process.stdout.write(JSON.stringify(${resultExpression}));`,
    ].join('\n');

    return JSON.parse(
        execFileSync(process.execPath, ['--input-type=module', '-e', probeScript], {
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        }),
    ) as unknown;
}

/** Reads the exported value `name` of the factory module in a fresh node process. */
function readExport(name: string): unknown {
    return evaluateInFactory('', `factory[${JSON.stringify(name)}]`);
}

/** Runs `buildConfig` and returns the error message it fails with. */
function buildError(options: Record<string, unknown>, factoryFile = factoryPath): string {
    try {
        buildConfig(options, factoryFile);
    } catch (error) {
        return String((error as { stderr?: string }).stderr);
    }

    throw new Error('Expected the factory to throw.');
}

/** The file a `transform` entry runs, from both its string and its `[path, options]` form. */
function transformerPath(entry: unknown): string {
    return Array.isArray(entry) ? String(entry[0]) : String(entry);
}

/**
 * The fixed part of a `moduleNameMapper` target, before its first capture-group reference.
 * Lets a spec check where `<admin>/src/$1` points by checking `<admin>/src`.
 */
function mappingPrefix(target: string): string {
    return target.split('$')[0].replace(/\/$/, '');
}

describe('extension-tooling jest factory', () => {
    let defaults: Config;

    beforeAll(() => {
        defaults = buildConfig({ rootDir: '/extension' });
    });

    it('requires rootDir', () => {
        expect(buildError({})).toContain('shopwareAdminJest requires the "rootDir" option.');
    });

    it('points every harness entry at a file in the installed Administration', () => {
        const harnessFiles = [
            defaults.resolver,
            defaults.testEnvironment,
            ...(defaults.setupFiles ?? []),
            ...(defaults.setupFilesAfterEnv ?? []),
            ...Object.values(defaults.transform ?? {}).map(transformerPath),
            ...Object.values(defaults.moduleNameMapper ?? {})
                .flat()
                .filter((target) => !target.includes('$')),
        ];

        expect(harnessFiles.filter((file) => !file || !path.isAbsolute(file) || !fs.existsSync(file))).toEqual([]);
    });

    it('sends every JS/TS file through the meta-glob-aware transformer, which picks Babel or SWC by content', () => {
        const scriptRules = Object.entries(defaults.transform ?? {}).filter(([pattern]) =>
            ['/admin/src/module/index.js', '/extension/src/module/index.ts'].some((file) => new RegExp(pattern).test(file)),
        );

        expect(scriptRules.map(([pattern, entry]) => [pattern, transformerPath(entry)])).toEqual([
            ['^.+\\.[jt]sx?$', path.join(realAdministrationRoot, 'test', 'transformer', 'metaGlobAwareTransformer.js')],
        ]);
    });

    it('resolves the presets and plugins of the meta-glob Babel transform into the installed Administration', () => {
        const scriptEntry = defaults.transform?.['^.+\\.[jt]sx?$'];
        const babelOptions = (
            (Array.isArray(scriptEntry) ? scriptEntry[1] : {}) as {
                babel?: { presets?: unknown[]; plugins?: unknown[] };
            }
        ).babel;
        const babelFiles = [...(babelOptions?.presets ?? []), ...(babelOptions?.plugins ?? [])].map(transformerPath);

        expect(babelFiles).toHaveLength(3);
        expect(babelFiles.filter((file) => !path.isAbsolute(file) || !fs.existsSync(file))).toEqual([]);
    });

    it('maps src/ imports into the Administration and leaves packages whose name starts with "src" alone', () => {
        expect(defaults.moduleNameMapper?.['^src/(.*)$']).toBe(`${path.join(realAdministrationRoot, 'src')}/$1`);
        expect(
            Object.keys(defaults.moduleNameMapper ?? {}).filter((key) => new RegExp(key).test('srcdoc-polyfill')),
        ).toEqual([]);
    });

    it('resolves every capture-group mapping prefix into the installed Administration', () => {
        const prefixes = Object.values(defaults.moduleNameMapper ?? {})
            .flat()
            .filter((target) => target.includes('$'))
            .map(mappingPrefix);

        expect(prefixes).not.toEqual([]);
        expect(
            prefixes.filter((prefix) => !path.isAbsolute(prefix) || !prefix.startsWith(`${realAdministrationRoot}/`)),
        ).toEqual([]);
    });

    it.each([
        'vue',
        '@vue/test-utils',
        'pinia',
        'vue-i18n',
        'vue-router',
    ])("pins %s to the Administration's copy, so an extension cannot load a second instance", (packageName) => {
        const target = defaults.moduleNameMapper?.[`^${packageName}$`];
        const packageRoot = path.join(realAdministrationRoot, 'node_modules', packageName);

        expect(`${String(target)}/`.startsWith(`${packageRoot}/`)).toBe(true);
    });

    it('runs the setup files of an extension after the harness', () => {
        const config = buildConfig({
            rootDir: '/extension',
            setupFiles: ['<rootDir>/polyfills.js'],
            setupFilesAfterEnv: ['<rootDir>/setup.js'],
        });

        expect(config.setupFiles).toEqual([
            ...(defaults.setupFiles ?? []),
            '<rootDir>/polyfills.js',
        ]);
        expect(config.setupFilesAfterEnv).toEqual([
            ...(defaults.setupFilesAfterEnv ?? []),
            '<rootDir>/setup.js',
        ]);
    });

    it('matches the mappings, transforms and module directories of an extension before the harness', () => {
        const config = buildConfig({
            rootDir: '/extension',
            moduleNameMapper: { '^MyPlugin/(.*)$': '<rootDir>/src/$1', '^vue$': '<rootDir>/vue.js' },
            transform: { '^.+\\.yaml$': '<rootDir>/yaml-transformer.js' },
            moduleDirectories: ['node_modules', '<rootDir>/tests/node_modules'],
        });

        expect(Object.keys(config.moduleNameMapper ?? {}).slice(0, 2)).toEqual([
            '^MyPlugin/(.*)$',
            '^vue$',
        ]);
        expect(config.moduleNameMapper?.['^vue$']).toBe('<rootDir>/vue.js');
        expect(Object.keys(config.moduleNameMapper ?? {})).toEqual(
            expect.arrayContaining(Object.keys(defaults.moduleNameMapper ?? {})),
        );
        expect(Object.keys(config.transform ?? {})).toEqual([
            '^.+\\.yaml$',
            ...Object.keys(defaults.transform ?? {}),
        ]);
        expect(config.moduleDirectories).toEqual([
            'node_modules',
            '<rootDir>/tests/node_modules',
            path.join(realAdministrationRoot, 'node_modules'),
        ]);
    });

    it('merges the globals and test environment options of an extension into the defaults', () => {
        const config = buildConfig({
            rootDir: '/extension',
            globals: { pluginName: 'MyPlugin' },
            testEnvironmentOptions: { url: 'http://localhost/admin' },
        });

        expect(config.globals).toEqual({ ...defaults.globals, pluginName: 'MyPlugin' });
        expect(config.testEnvironmentOptions).toEqual({
            ...defaults.testEnvironmentOptions,
            url: 'http://localhost/admin',
        });
    });

    it('pins globals.adminPath to its own Administration and keeps the other globals of an extension', () => {
        const config = buildConfig({
            rootDir: '/extension',
            globals: { adminPath: '/other/administration', pluginName: 'MyPlugin' },
        });

        expect(config.globals).toEqual({
            ...defaults.globals,
            adminPath: realAdministrationRoot,
            pluginName: 'MyPlugin',
        });
    });

    it("pins the vue-jest tsConfig to the Administration's tsconfig and keeps the other vue-jest options of an extension", () => {
        const config = buildConfig({
            rootDir: '/extension',
            globals: { 'vue-jest': { tsConfig: '/extension/tsconfig.json', compilerOptions: { isProduction: true } } },
        });

        expect(config.globals?.['vue-jest']).toEqual({
            tsConfig: path.join(realAdministrationRoot, 'tsconfig.json'),
            compilerOptions: { isProduction: true },
        });
    });

    it('defaults transformIgnorePatterns to the exported HOST_TRANSFORM_IGNORE_PATTERNS', () => {
        expect(defaults.transformIgnorePatterns).toEqual(readExport('HOST_TRANSFORM_IGNORE_PATTERNS'));
    });

    it('builds HOST_TRANSFORM_IGNORE_PATTERNS from the exported HOST_TRANSFORMED_PACKAGES', () => {
        const packages = readExport('HOST_TRANSFORMED_PACKAGES') as string[];

        expect(packages).toContain('@shopware-ag/meteor-component-library');
        expect(defaults.transformIgnorePatterns).toEqual([`/node_modules/(?!(${packages.join('|')})/)`]);
    });

    it('transforms an additional package with the pattern the README builds from HOST_TRANSFORMED_PACKAGES', () => {
        const packages = readExport('HOST_TRANSFORMED_PACKAGES') as string[];
        const ignored = new RegExp(`/node_modules/(?!(${[...packages, 'nanoid'].join('|')})/)`);

        expect(ignored.test('/extension/node_modules/left-pad/index.js')).toBe(true);
        expect(ignored.test('/extension/node_modules/nanoid/index.js')).toBe(false);
        expect(packages.filter((name) => ignored.test(`/administration/node_modules/${name}/index.js`))).toEqual([]);
    });

    it.each([
        ['HOST_TRANSFORM_IGNORE_PATTERNS', '/extension/'],
        ['HOST_TRANSFORMED_PACKAGES', 'nanoid'],
    ])('keeps the exported %s unchanged for later configs when an extension mutates it', (name, entry) => {
        const result = evaluateInFactory(
            [
                'let mutationError = null;',
                `try { factory.${name}.push(${JSON.stringify(entry)}); } catch (error) { mutationError = error.name; }`,
            ].join('\n'),
            "{ mutationError, patterns: factory.shopwareAdminJest({ rootDir: '/extension' }).transformIgnorePatterns }",
        );

        expect(result).toEqual({ mutationError: 'TypeError', patterns: defaults.transformIgnorePatterns });
    });

    it('lets an allowlisted option replace the default', () => {
        const config = buildConfig({
            rootDir: '/extension',
            testTimeout: 20000,
            transformIgnorePatterns: ['/node_modules/(?!my-esm-package/)'],
        });

        expect(config.testTimeout).toBe(20000);
        expect(config.transformIgnorePatterns).toEqual(['/node_modules/(?!my-esm-package/)']);
    });

    it.each([
        'testEnvironment',
        'resolver',
        'preset',
    ])('rejects the "%s" option, which is not on the allowlist', (option) => {
        const message = buildError({ rootDir: '/extension', [option]: 'anything' });

        expect(message).toContain(`shopwareAdminJest does not accept the option "${option}".`);
        expect(message).toContain('Accepted options: rootDir, setupFiles,');
    });

    describe('without the generated files', () => {
        let projectRoot: string;
        let copiedFactoryPath: string;

        beforeEach(() => {
            projectRoot = createTempProject('sw-tooling-jest-factory-');
            copiedFactoryPath = path.join(projectRoot, 'administration', 'extension-tooling', 'jest.mjs');
            writeFile(copiedFactoryPath, fs.readFileSync(factoryPath, 'utf8'));
        });

        afterEach(() => {
            cleanupTempProject(projectRoot);
        });

        it('names the command for every missing file', () => {
            const error = buildError({ rootDir: '/extension' }, copiedFactoryPath);

            expect(error).toContain(
                `component-imports.js: run \`npm run unit-setup\` in ${path.join(projectRoot, 'administration')}`,
            );
            expect(error).toContain('entity-schema.json: run `bin/console administration:generate-entity-schema-types`');
        });

        it('only names the files that are missing', () => {
            writeFile(path.join(projectRoot, 'administration', 'test', '_mocks_', 'entity-schema.json'), '{}');

            const error = buildError({ rootDir: '/extension' }, copiedFactoryPath);

            expect(error).toContain('component-imports.js');
            expect(error).not.toContain('entity-schema.json');
        });
    });
});
