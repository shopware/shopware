/**
 * @sw-package framework
 *
 * Acceptance spec of the comment-only 6.8 codemods. A plugin with one realistic extension file per codemod is upgraded
 * once with `--write`. Like `codemods.spec.ts`, it checks per codemod the count and the spots it marks, each with a
 * codemod comment of its id. The rule specs pin the exact comments. A second run changes nothing and keeps listing
 * the spots as manual work.
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

const FILES = CASES.flatMap((upgradeCase) => Object.keys(upgradeCase.files));

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

/** The spots the report lists as manual work, as `<file below the administration folder>:<line>`, sorted. */
function manualSpots(extension: ExtensionUpgrade, id: string): string[] {
    return report(extension, id)
        .manual.map(({ file, line }) => `${file.replace('src/Resources/app/administration/', '')}:${line}`)
        .sort();
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
                writeFile(path.join(projectRoot, ADMINISTRATION, file), lines);
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

    it.each(CASES)('$id: $behaviour', ({ id, spots }) => {
        expect(report(firstRun, id).changes).toBe(spots.length);
        expect(manualSpots(firstRun, id)).toEqual(spots);

        spots.forEach((spot) => {
            const [file, line] = spot.split(':');
            const marked = read(file)
                .split('\n')
                .slice(Math.max(Number(line) - 2, 0), Number(line));

            expect(marked.join('\n')).toContain(`TODO Codemod(6.8, ${id}): `);
        });
    });

    it('a second run changes no file, reports no changes and keeps the spots as manual work', async () => {
        commitAll(path.join(projectRoot, PLUGIN));

        const before = FILES.map(read);
        const [secondRun] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        expect(FILES.map(read)).toEqual(before);
        expect(Object.fromEntries(CASES.map(({ id }) => [id, report(secondRun, id).changes]))).toEqual(
            Object.fromEntries(CASES.map(({ id }) => [id, 0])),
        );
        expect(Object.fromEntries(CASES.map(({ id }) => [id, manualSpots(secondRun, id)]))).toEqual(
            Object.fromEntries(CASES.map(({ id, spots }) => [id, spots])),
        );
    });
});
