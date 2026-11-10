/**
 * @sw-package framework
 *
 * The golden test of the 6.8 codemods: whatever implements them must turn the fixture plugin into the
 * expected files, list the same spots as manual work, and change nothing on a second run.
 */

import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { upgradeExtensions } from '../upgrade';
import { CodemodSelectionError } from '../upgrade-codemods';
import type { UpgradeLevel } from '../upgrade-codemods';
import { cleanupTempProject, createSkeletonAdmin, createTempProject, writeFile, writePluginsConfig } from '../test-helpers';
import { commitAll, useRealToolchain } from './fixtures';
import { GOLDEN_EXPECTED, GOLDEN_MANUAL, GOLDEN_PLUGIN, GOLDEN_SOURCES } from './golden-fixture';

jest.setTimeout(240_000);

const REVIEW_LINE = /^\s*(<!--|\/\*)\s*TODO Codemod\b.*(-->|\*\/)\s*$/;

/**
 * Turns every stack of review comments into one marker line, so only the place of a comment counts
 * and not its wording.
 */
function normalize(text: string): string[] {
    const lines: string[] = [];

    text.replace(/\n$/, '')
        .split('\n')
        .forEach((line) => {
            if (!REVIEW_LINE.test(line)) {
                lines.push(line);

                return;
            }

            if (lines[lines.length - 1]?.trim() !== 'TODO Codemod') {
                lines.push(`${line.match(/^\s*/)?.[0] ?? ''}TODO Codemod`);
            }
        });

    return lines;
}

describe('scripts/extensionTooling/upgrade golden test', () => {
    let projectRoot: string;
    let administrationRoot: string;
    const pluginRoot = () => path.join(projectRoot, GOLDEN_PLUGIN);
    const read = (file: string) => fs.readFileSync(path.join(pluginRoot(), file), 'utf8');
    const upgrade = (options: { write?: boolean; level?: UpgradeLevel } = {}) => {
        return upgradeExtensions({ projectRoot, administrationRoot, target: '6.8', ...options });
    };

    beforeEach(() => {
        projectRoot = createTempProject('sw-tooling-upgrade-golden-');
        administrationRoot = createSkeletonAdmin(projectRoot);
        useRealToolchain(administrationRoot);
        writeFile(path.join(projectRoot, GOLDEN_PLUGIN, 'composer.json'), '{}\n');
        Object.entries(GOLDEN_SOURCES).forEach(([file, lines]) => {
            writeFile(path.join(projectRoot, GOLDEN_PLUGIN, file), lines);
        });
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'AcmeGolden',
                basePath: `${GOLDEN_PLUGIN}/src`,
                administrationPath: 'Resources/app/administration/src',
            },
        ]);
        commitAll(pluginRoot());
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('lists the expected spots as manual work in a dry run and writes nothing', async () => {
        const result = await upgrade();
        const spots = result.extensions[0].codemods.flatMap((report) => {
            return report.manual.map((spot) => `${spot.file}:${spot.line ?? 0}`);
        });

        expect(result.exitCode).toBe(0);
        expect(result.extensions[0].errors).toEqual([]);
        expect([...new Set(spots)].sort()).toEqual([...GOLDEN_MANUAL].sort());
        Object.entries(GOLDEN_SOURCES).forEach(([file, lines]) => {
            expect(read(file)).toBe(`${lines.join('\n')}\n`);
        });
    });

    it('turns every file into its expected state', async () => {
        const result = await upgrade({ write: true });

        expect(result.extensions[0].errors).toEqual([]);
        Object.entries(GOLDEN_EXPECTED).forEach(([file, lines]) => {
            expect({ file, lines: normalize(read(file)) }).toEqual({ file, lines });
        });
    });

    it('changes nothing on a second run and still lists the spots', async () => {
        await upgrade({ write: true });
        commitAll(pluginRoot());
        const again = await upgrade({ write: true });
        const status = spawnSync('git', ['status', '--porcelain'], { cwd: pluginRoot(), encoding: 'utf8' });
        const spots = again.extensions[0].codemods.flatMap((report) => report.manual);

        expect(status.stdout).toBe('');
        expect(spots.length).toBeGreaterThan(0);
    });

    it('writes no review comment for manual work at the safe level', async () => {
        await upgrade({ write: true, level: 'safe' });

        expect(read('src/Resources/app/administration/src/main.js')).not.toContain('TODO Codemod');
    });

    it('rejects an unknown level', async () => {
        await expect(upgrade({ level: 'all' as UpgradeLevel })).rejects.toThrow(CodemodSelectionError);
    });
});
