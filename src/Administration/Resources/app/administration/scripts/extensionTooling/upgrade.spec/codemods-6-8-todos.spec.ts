/**
 * @sw-package framework
 *
 * Acceptance spec of the comment-only 6.8 codemods. A plugin with one realistic extension file per codemod is upgraded
 * once with `--write`. Each case lists its files as the upgrade must leave them, with the codemod comments written out
 * word for word; the plugin starts with the same files without those comment lines. A second run changes nothing and
 * keeps listing the spots as manual work.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, commitAll, createUpgradeProject } from './fixtures';
import { TODO_CODEMOD_CASES as CASES } from './codemods-6-8-todos.fixture';

jest.setTimeout(120_000);

const ADMINISTRATION = `${PLUGIN}/src/Resources/app/administration`;
const MARKER = 'TODO Codemod(6.8, ';

const FILES = CASES.flatMap((upgradeCase) => Object.keys(upgradeCase.files));

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

describe('scripts/extensionTooling/upgrade 6.8 comment-only codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let firstRun: ExtensionUpgrade;

    const read = (file: string) => fs.readFileSync(path.join(projectRoot, ADMINISTRATION, file), 'utf8');

    beforeAll(async () => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-todos-'));

        for (const { files } of CASES) {
            for (const [file, lines] of Object.entries(files)) {
                writeFile(
                    path.join(projectRoot, ADMINISTRATION, file),
                    lines.filter((line) => !line.includes(MARKER)),
                );
            }
        }

        commitAll(path.join(projectRoot, PLUGIN));
        [firstRun] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        if (firstRun.errors.length > 0) {
            throw new Error(firstRun.errors.join('\n'));
        }
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it.each(CASES)('$id: $behaviour', ({ files }) => {
        for (const [file, lines] of Object.entries(files)) {
            expect(read(file)).toBe(`${lines.join('\n')}\n`);
        }
    });

    it('a second run changes no file, reports no changes and keeps the spots as manual work', async () => {
        commitAll(path.join(projectRoot, PLUGIN));

        const before = FILES.map(read);
        const [secondRun] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        expect(FILES.map(read)).toEqual(before);

        for (const { id } of CASES) {
            expect({ id, changes: report(secondRun, id).changes, manual: report(secondRun, id).manual.length }).toEqual({
                id,
                changes: 0,
                manual: report(firstRun, id).changes,
            });
        }
    });
});
