/**
 * @sw-package framework
 *
 * The git check before --write: a run must stay undoable with git, and
 * probing the extension repository must not execute its config.
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { upgradeExtensions } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

describe('scripts/extensionTooling/upgrade git safety', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-git-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('refuses to write into an extension with uncommitted changes', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'wip.js'), ['export {};']);

        const result = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(result.exitCode).toBe(1);
        expect(result.fatalDiagnostics.join('\n')).toContain(`${PLUGIN} has uncommitted changes`);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');
    });

    it('refuses to write into an extension git cannot restore unless the check is skipped', async () => {
        fs.rmSync(path.join(projectRoot, PLUGIN, '.git'), { recursive: true, force: true });

        const refused = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(refused.exitCode).toBe(1);
        expect(refused.fatalDiagnostics.join('\n')).toContain(`${PLUGIN} is not tracked by git`);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<sw-button');

        const forced = await upgradeExtensions({ projectRoot, administrationRoot, write: true, skipGitCheck: true });

        expect(forced.exitCode).toBe(0);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
    });

    it('does not run the fsmonitor command of the extension git config while checking it', async () => {
        const marker = path.join(projectRoot, 'fsmonitor-ran');
        const hook = path.join(projectRoot, 'fsmonitor-hook.sh');

        fs.writeFileSync(hook, `#!/bin/sh\ntouch '${marker}'\n`, { mode: 0o755 });
        spawnSync('git', ['config', 'core.fsmonitor', hook], { cwd: path.join(projectRoot, PLUGIN) });

        const result = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(result.exitCode).toBe(0);
        expect(fs.existsSync(marker)).toBe(false);
    });
});
