/**
 * @sw-package framework
 *
 * The managed .gitignore block: fenced ownership inside a user-owned file,
 * never rewriting user lines, respecting deletion and opt-out.
 */

import fs from 'fs';
import path from 'path';
import { setupExtensionTooling } from '../setup';
import { MANAGED_BLOCK_BEGIN, MANAGED_BLOCK_END, writeManagedBlock } from '../shared';
import {
    cleanupTempProject,
    createSkeletonAdmin,
    createTempProject,
    syntheticEntitySchema,
    writeFile,
    writePluginsConfig,
} from '../test-helpers';

describe('scripts/extensionTooling/setup managed .gitignore block', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-setup-gitignore-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        fs.mkdirSync(path.join(administrationRoot, 'src'), { recursive: true });
        writeFile(path.join(administrationRoot, 'src', 'entity-schema-definition.d.ts'), syntheticEntitySchema);
        writePluginsConfig(projectRoot, []);
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    function gitignorePath(): string {
        return path.join(projectRoot, '.gitignore');
    }

    it('creates a fenced block covering the generated root files when no .gitignore exists', () => {
        const result = setupExtensionTooling({ projectRoot, administrationRoot });
        const content = fs.readFileSync(gitignorePath(), 'utf8');

        expect(result.manifest.gitignore).toEqual({ state: 'managed', optedOut: false });
        expect(content).toContain(MANAGED_BLOCK_BEGIN);
        expect(content).toContain('/tsconfig.json');
        expect(content).toContain('/eslint.config.mjs');
        expect(content).toContain('/.zed/');
        expect(content).toContain(MANAGED_BLOCK_END);

        // Idempotent: the second run leaves the block unchanged.
        const secondRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(secondRun.changed).toBe(false);
    });

    it('appends the block below existing user lines, preserving them byte-identically', () => {
        const userContent = '# my rules\n/var\n/node_modules\n';

        writeFile(gitignorePath(), userContent);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const content = fs.readFileSync(gitignorePath(), 'utf8');

        expect(content.startsWith(userContent)).toBe(true);
        expect(content).toContain(MANAGED_BLOCK_BEGIN);
    });

    it('rewrites only between the markers when the block content drifted', () => {
        writeFile(gitignorePath(), [
            '/user-line',
            MANAGED_BLOCK_BEGIN,
            '/stale-entry',
            MANAGED_BLOCK_END,
            '/tail-line',
        ]);
        setupExtensionTooling({ projectRoot, administrationRoot });

        const content = fs.readFileSync(gitignorePath(), 'utf8');

        expect(content).toContain('/user-line');
        expect(content).toContain('/tail-line');
        expect(content).toContain('/tsconfig.json');
        expect(content).not.toContain('/stale-entry');
    });

    it('stands down when the entries are already covered without a block', () => {
        const covered = '/tsconfig.json\n/eslint.config.mjs\n/.zed/\n';

        writeFile(gitignorePath(), covered);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.manifest.gitignore.state).toBe('skipped');
        expect(fs.readFileSync(gitignorePath(), 'utf8')).toBe(covered);
    });

    it('respects a user-deleted block and never re-adds it', () => {
        setupExtensionTooling({ projectRoot, administrationRoot });
        writeFile(gitignorePath(), '# user pruned the managed block\n/var\n');

        const rerun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(rerun.manifest.gitignore.state).toBe('skipped');
        expect(fs.readFileSync(gitignorePath(), 'utf8')).not.toContain(MANAGED_BLOCK_BEGIN);
        expect(rerun.instructions.join('\n')).toContain('managed ignore block was removed');
    });

    it('persists --no-gitignore as a standing opt-out', () => {
        const optedOut = setupExtensionTooling({ projectRoot, administrationRoot, noGitignore: true });

        expect(optedOut.manifest.gitignore).toEqual({ state: 'skipped', optedOut: true });
        expect(fs.existsSync(gitignorePath())).toBe(false);

        // Later runs without the flag stay opted out via the manifest.
        const laterRun = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(laterRun.manifest.gitignore.optedOut).toBe(true);
        expect(fs.existsSync(gitignorePath())).toBe(false);
    });

    it('reports a conflict for a malformed fence and leaves the file alone', () => {
        const malformed = `${MANAGED_BLOCK_BEGIN}\n/orphaned\n`;

        writeFile(gitignorePath(), malformed);

        const result = setupExtensionTooling({ projectRoot, administrationRoot });

        expect(result.manifest.gitignore.state).toBe('conflict');
        expect(fs.readFileSync(gitignorePath(), 'utf8')).toBe(malformed);
        expect(result.instructions.join('\n')).toContain('malformed managed block');
    });

    it('writeManagedBlock reports unchanged content without rewriting', () => {
        const filePath = path.join(projectRoot, 'block-target');

        expect(writeManagedBlock(filePath, ['/a']).state).toBe('created');
        expect(writeManagedBlock(filePath, ['/a']).state).toBe('unchanged');
        expect(writeManagedBlock(filePath, ['/b']).state).toBe('updated');
    });
});
