/**
 * @sw-package framework
 *
 * CLI entry behavior of `admin:upgrade-extensions`: selection without a TTY,
 * usage errors, and the report hints after a write.
 */

import fs from 'fs';
import path from 'path';
import { runUpgradeCli } from '../upgrade';
import { cleanupTempProject } from '../test-helpers';
import { stripAnsi } from '../report.spec/helpers';
import { PLUGIN, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const TERMINAL_STREAMS = [process.stdin, process.stdout];

describe('scripts/extensionTooling/upgrade runUpgradeCli', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let output: string[];
    let terminalDescriptors: (PropertyDescriptor | undefined)[];

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-cli-'));
        output = [];
        jest.spyOn(console, 'log').mockImplementation((line: string) => output.push(stripAnsi(line)));
        jest.spyOn(console, 'error').mockImplementation((line: string) => output.push(stripAnsi(line)));

        // An in-band run from a terminal would otherwise open the interactive picker.
        terminalDescriptors = TERMINAL_STREAMS.map((stream) => Object.getOwnPropertyDescriptor(stream, 'isTTY'));
        TERMINAL_STREAMS.forEach((stream) => Object.defineProperty(stream, 'isTTY', { value: false, configurable: true }));
    });

    afterEach(() => {
        TERMINAL_STREAMS.forEach((stream, index) => {
            const descriptor = terminalDescriptors[index];

            if (descriptor) {
                Object.defineProperty(stream, 'isTTY', descriptor);
            } else {
                delete (stream as { isTTY?: boolean }).isTTY;
            }
        });
        jest.restoreAllMocks();
        cleanupTempProject(projectRoot);
    });

    it('prints the dry-run report with the command that applies it', async () => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--only=AcmeUpgrade',
        ]);
        const report = output.join('\n');

        expect(exitCode).toBe(0);
        expect(report).toContain('Dry run, nothing was written.');
        expect(report).toContain('bin/console administration:upgrade-extensions -- --only=AcmeUpgrade --target=6.8 --write');
    });

    it('says so when a non-interactive run upgrades every writable extension', async () => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
        ]);

        expect(exitCode).toBe(0);
        expect(output[0]).toBe(
            'Not an interactive terminal and no selection given — upgrading all writable extensions. ' +
                'Pass --only=<name[,name]> or --all to be explicit.',
        );
        expect(output.join('\n')).toContain('AcmeUpgrade');
    });

    it('refuses to write without a selection outside an interactive terminal', async () => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--write',
        ]);

        expect(exitCode).toBe(2);
        expect(output.join('\n')).toContain('--write needs --only=<name[,name]> or --all');
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');
    });

    it.each(['--only=', '--only=,'])('refuses %s instead of selecting every extension', async (only) => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            only,
            '--write',
        ]);

        expect(exitCode).toBe(2);
        expect(output.join('\n')).toMatch(/--only (requires a value|needs at least one extension name)/);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');
    });

    it('writes every writable extension with --all and explains how to review and undo it', async () => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--all',
            '--write',
        ]);
        const report = output.join('\n');

        expect(exitCode).toBe(0);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
        expect(report).toContain('Review them with: git diff\n');
        expect(report).toContain('In each extension folder, undo them with: git checkout . && git clean -f');
    });

    it('points to git status when the SFC migration wrote new drafts', async () => {
        await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--only=AcmeUpgrade',
            '--with=sfc-migration',
            '--write',
        ]);

        expect(output.join('\n')).toContain('Review them with: git diff, and git status for the new .vue drafts');
    });

    it('does not suggest git clean after the git check was skipped', async () => {
        fs.rmSync(path.join(projectRoot, PLUGIN, '.git'), { recursive: true, force: true });

        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--only=AcmeUpgrade',
            '--write',
            '--skip-git-check',
        ]);

        expect(exitCode).toBe(0);
        expect(output.join('\n')).toContain('Wrote ');
        expect(output.join('\n')).not.toContain('git clean');
    });

    it('exits with a usage error for an unknown target', async () => {
        const exitCode = await runUpgradeCli([
            `--project-root=${projectRoot}`,
            `--administration-root=${administrationRoot}`,
            '--only=AcmeUpgrade',
            '--target=7.0',
        ]);

        expect(exitCode).toBe(2);
        expect(output.join('\n')).toContain('ships codemods up to 6.8');
    });

    it('lists the codemods per target', async () => {
        expect(await runUpgradeCli(['--list'])).toBe(0);
        expect(output.join('\n')).toMatch(/Shopware 6\.8[\s\S]*Options API → SFC\s+opt-in, --with=sfc-migration/);
    });
});
