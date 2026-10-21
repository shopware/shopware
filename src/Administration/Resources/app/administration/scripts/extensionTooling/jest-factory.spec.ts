/**
 * @sw-package framework
 *
 * Guards the Jest factory contract: every harness path it hands to Jest
 * exists, an extension's options extend the harness instead of replacing it,
 * and a missing harness fails with the step that fixes it. The factory is an
 * .mjs module Jest cannot import directly, so a node subprocess builds each
 * config and serializes it.
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

        expect(harnessFiles.filter((file) => !file || !fs.existsSync(file))).toEqual([]);
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

    it('merges globals and lets every other option replace the default', () => {
        const config = buildConfig({
            rootDir: '/extension',
            globals: { pluginName: 'MyPlugin' },
            testTimeout: 20000,
            testEnvironment: 'node',
        });

        expect(config.globals).toEqual({ ...defaults.globals, pluginName: 'MyPlugin' });
        expect(config.testTimeout).toBe(20000);
        expect(config.testEnvironment).toBe('node');
    });

    describe('without a usable harness', () => {
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

        it('explains that the Composer package ships no harness', () => {
            expect(buildError({ rootDir: '/extension' }, copiedFactoryPath)).toContain(
                'The Composer package of shopware/administration excludes its test/ folder',
            );
        });

        it('names the setup step when the component imports were never generated', () => {
            fs.mkdirSync(path.join(projectRoot, 'administration', 'test', '_setup'), { recursive: true });

            expect(buildError({ rootDir: '/extension' }, copiedFactoryPath)).toContain('npm run unit-setup');
        });
    });
});
