/**
 * @sw-package framework
 *
 * The write-ownership contract: marker-owned files are rewritten, user-owned
 * files are never touched (instructions are printed instead), a second run
 * changes nothing, and --check reports drift without writing.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { GENERATED_MARKER } from '../shared';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { createSetupProject, writeDefaultFixtures } from './fixtures';

describe('scripts/extensionTooling/setup file ownership', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createSetupProject('sw-tooling-setup-ownership-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('never overwrites user-owned files and prints integration instructions instead', () => {
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(projectRoot, 'tsconfig.json'), '{"compilerOptions":{"strict":false}}\n');
        writeFile(path.join(projectRoot, '.vscode/settings.json'), '{"editor.tabSize": 2}\n');

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('"strict":false');
        expect(fs.readFileSync(path.join(projectRoot, '.vscode/settings.json'), 'utf8')).toContain('editor.tabSize');
        expect(result.manifest.rootConfigs.tsconfig).toBe('conflict');
        expect(result.manifest.ideBootstraps['.vscode/settings.json']).toBe('skipped');
        expect(result.instructions.join('\n')).toContain('references');
        expect(result.instructions.join('\n')).toContain('typescript.tsdk');
    });

    it('rewrites marker-owned files when their content is outdated', () => {
        writeDefaultFixtures(projectRoot);
        writeFile(path.join(projectRoot, 'tsconfig.json'), `// ${GENERATED_MARKER}\n{"files":[],"references":[]}\n`);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const rootWrite = result.writes.find((write) => write.file === 'tsconfig.json');

        expect(rootWrite?.state).toBe('updated');
        expect(fs.readFileSync(path.join(projectRoot, 'tsconfig.json'), 'utf8')).toContain('solution-style index');
    });

    it('is idempotent: a second run changes nothing', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const secondRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(secondRun.changed).toBe(false);
        expect(secondRun.writes.filter((write) => write.state === 'created' || write.state === 'updated')).toEqual([]);
        expect(secondRun.staleFiles).toEqual([]);
    });

    it('supports --check as a validate-only mode that writes nothing', () => {
        writeDefaultFixtures(projectRoot);
        setupExtensionTooling({ projectRoot, administrationRoot });
        fs.rmSync(path.join(projectRoot, 'eslint.config.mjs'));

        const checkRun = setupExtensionTooling({ projectRoot, administrationRoot, checkOnly: true });

        expect(checkRun.changed).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, 'eslint.config.mjs'))).toBe(false);
    });
});
