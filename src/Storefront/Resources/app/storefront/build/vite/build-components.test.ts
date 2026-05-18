// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

const require = createRequire(import.meta.url);
const { getNpmInstallCommand, shouldAllowInstallScripts, shouldInstallNpmDependencies } = require('./build-components.js') as {
    getNpmInstallCommand: (
        storefrontAppDir: string,
        env?: Record<string, string | undefined>
    ) => { cmd: string; args: string[]; scriptsAllowed: boolean };
    shouldAllowInstallScripts: (env?: Record<string, string | undefined>) => boolean;
    shouldInstallNpmDependencies: (
        storefrontAppDir: string,
        env?: Record<string, string | undefined>
    ) => boolean;
};

describe('build-components npm install policy', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'build-components-policy-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('disables scripts by default even when lockfile is present', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'with-lock');
        fs.mkdirSync(storefrontAppDir, { recursive: true });
        fs.writeFileSync(path.join(storefrontAppDir, 'package-lock.json'), '{}');

        const result = getNpmInstallCommand(storefrontAppDir, {});

        expect(result).toEqual({
            cmd: 'npm',
            args: ['ci', '--include=dev', '--ignore-scripts'],
            scriptsAllowed: false,
        });
    });

    it('falls back to npm install and still disables scripts without a lockfile', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'no-lock');
        fs.mkdirSync(storefrontAppDir, { recursive: true });

        const result = getNpmInstallCommand(storefrontAppDir, {});

        expect(result).toEqual({
            cmd: 'npm',
            args: ['install', '--prefer-offline', '--include=dev', '--ignore-scripts'],
            scriptsAllowed: false,
        });
    });

    it('allows lifecycle scripts only when explicitly opted in', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'opt-in');
        fs.mkdirSync(storefrontAppDir, { recursive: true });
        fs.writeFileSync(path.join(storefrontAppDir, 'package-lock.json'), '{}');

        const result = getNpmInstallCommand(storefrontAppDir, {
            ALLOW_EXTENSION_INSTALL_SCRIPTS: '1',
        });

        expect(result).toEqual({
            cmd: 'npm',
            args: ['ci', '--include=dev'],
            scriptsAllowed: true,
        });
    });

    it('treats values other than "1" as disabled', () => {
        expect(shouldAllowInstallScripts({ ALLOW_EXTENSION_INSTALL_SCRIPTS: 'true' })).toBe(false);
        expect(shouldAllowInstallScripts({ ALLOW_EXTENSION_INSTALL_SCRIPTS: '0' })).toBe(false);
        expect(shouldAllowInstallScripts({})).toBe(false);
    });

    it('skips dependency install when node_modules already exists', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'with-node-modules');
        fs.mkdirSync(path.join(storefrontAppDir, 'node_modules'), { recursive: true });

        expect(shouldInstallNpmDependencies(storefrontAppDir, {})).toBe(false);
    });

    it('installs dependencies when node_modules is missing', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'without-node-modules');
        fs.mkdirSync(storefrontAppDir, { recursive: true });

        expect(shouldInstallNpmDependencies(storefrontAppDir, {})).toBe(true);
    });

    it('allows forcing dependency install via env', () => {
        const storefrontAppDir = path.join(fixtureRoot, 'forced-install');
        fs.mkdirSync(path.join(storefrontAppDir, 'node_modules'), { recursive: true });

        expect(shouldInstallNpmDependencies(storefrontAppDir, { FORCE_COMPONENT_DEP_INSTALL: '1' })).toBe(true);
    });
});
