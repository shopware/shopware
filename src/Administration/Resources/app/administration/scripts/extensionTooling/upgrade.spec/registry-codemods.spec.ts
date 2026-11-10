/**
 * @sw-package framework
 *
 * The 6.8 codemods of the deprecation registry: the run level, review comments for spots that
 * need a human, and that a later run lists those spots again without a second comment.
 */

import path from 'path';
import { upgradeExtensions } from '../upgrade';
import { CodemodSelectionError } from '../upgrade-codemods';
import type { UpgradeLevel } from '../upgrade-codemods';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const STATE_TODO = '/* TODO Codemod: Vuex is removed in 6.8.';

function countOf(text: string, part: string): number {
    return text.split(part).length - 1;
}

describe('scripts/extensionTooling/upgrade registry codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-registry-'));

        writeFile(path.join(projectRoot, SOURCE, 'page/acme-state/index.js'), [
            'export default {',
            '    computed: {',
            '        count() {',
            "            return Shopware.State.get('acme').count;",
            '        },',
            '    },',
            '};',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'page/acme-condition/acme-condition.html.twig'), [
            '{% block sw_condiiton_date_range_field_to_date %}',
            '    {% parent %}',
            '{% endblock %}',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    const upgrade = (options: { write?: boolean; level?: UpgradeLevel } = {}) => {
        return upgradeExtensions({ projectRoot, administrationRoot, target: '6.8', ...options });
    };

    const reportOf = async (result: ReturnType<typeof upgrade>, id: string) => {
        return (await result).extensions[0].codemods.find((report) => report.codemod.id === id);
    };

    it('lists a spot that needs a human as manual work already in a dry run', async () => {
        const result = upgrade();
        const apiUsage = await reportOf(result, 'api-usage');

        expect((await result).level).toBe('partial');
        expect(apiUsage?.manual).toEqual([
            expect.objectContaining({ file: `src/Resources/app/administration/src/page/acme-state/index.js`, line: 4 }),
        ]);
        expect(readSource(projectRoot, 'page/acme-state/index.js')).not.toContain('TODO Codemod');
    });

    it('writes the review comment once and keeps listing the spot', async () => {
        await upgrade({ write: true });
        const written = readSource(projectRoot, 'page/acme-state/index.js');

        expect(countOf(written, STATE_TODO)).toBe(1);

        commitAll(path.join(projectRoot, PLUGIN));
        const again = upgrade({ write: true });

        expect(countOf(readSource(projectRoot, 'page/acme-state/index.js'), STATE_TODO)).toBe(1);
        expect((await reportOf(again, 'api-usage'))?.manual).toHaveLength(1);
        expect((await reportOf(again, 'api-usage'))?.changes).toBe(0);
    });

    it('renames overrides of renamed Twig blocks', async () => {
        await upgrade({ write: true });

        expect(readSource(projectRoot, 'page/acme-condition/acme-condition.html.twig')).toContain(
            '{% block sw_condition_date_range_field_to_date %}',
        );
    });

    it('writes no review comment at the safe level but still lists the spot', async () => {
        const result = upgrade({ write: true, level: 'safe' });

        expect(readSource(projectRoot, 'page/acme-state/index.js')).not.toContain('TODO Codemod');
        expect((await reportOf(result, 'api-usage'))?.manual).toHaveLength(1);
        expect(readSource(projectRoot, 'page/acme-condition/acme-condition.html.twig')).toContain(
            'sw_condition_date_range_field_to_date',
        );
    });

    it('rejects an unknown level', async () => {
        await expect(upgrade({ level: 'all' as UpgradeLevel })).rejects.toThrow(CodemodSelectionError);
    });
});
