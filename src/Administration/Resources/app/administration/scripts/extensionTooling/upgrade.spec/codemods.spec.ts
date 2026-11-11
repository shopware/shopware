/**
 * @sw-package framework
 *
 * Which codemods run and what they rewrite: dry run versus write,
 * targets, opt-in codemods, and source folders without lintable files.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import { CodemodSelectionError } from '../upgrade-codemods';
import { cleanupTempProject, writeFile, writePluginsConfig } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

describe('scripts/extensionTooling/upgrade codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-codemods-'));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('reports what each codemod would change without writing anything', async () => {
        const before = readSource(projectRoot, 'page/acme-list/acme-list.html.twig');

        const result = await upgradeExtensions({ projectRoot, administrationRoot });
        const [extension] = result.extensions;
        const report = (id: string) => extension.codemods.find((candidate) => candidate.codemod.id === id);

        expect(result.target).toBe('6.8');
        expect(result.exitCode).toBe(0);
        expect(report('mt-components')).toMatchObject({ changes: 1, files: 1 });
        expect(report('tc-translation')).toMatchObject({ changes: 1, files: 1 });
        expect(report('vite-8-glob')).toMatchObject({ changes: 1, files: 1 });
        expect(report('vite-8-glob')?.manual).toEqual([
            expect.objectContaining({ file: 'src/Resources/app/administration/src/main.js', line: 2 }),
        ]);
        expect(report('sfc-migration')).toMatchObject({ enabled: false, changes: 1 });
        expect(extension.changedFiles).toEqual([
            'src/Resources/app/administration/src/main.js',
            'src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
            'src/Resources/app/administration/src/page/acme-list/index.js',
        ]);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toBe(before);
    });

    it('counts a file once when several codemods change it', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'page/acme-list/acme-list.html.twig'), [
            '{% block acme_list %}',
            '<sw-button :label="$tc(\'acme.list.save\')" />',
            '{% endblock %}',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));

        const result = await upgradeExtensions({ projectRoot, administrationRoot, target: '6.8', write: true });

        expect(result.extensions[0].changedFiles).toContain(
            'src/Resources/app/administration/src/page/acme-list/acme-list.html.twig',
        );
        expect(result.extensions[0].changedFiles).toHaveLength(3);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain(
            '<mt-button :label="$t(\'acme.list.save\')"',
        );
    });

    it('writes the fixes and keeps what needs a human for the report', async () => {
        const result = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(result.exitCode).toBe(0);
        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
        expect(readSource(projectRoot, 'page/acme-list/index.js')).toContain("this.$t('acme.list.title')");
        expect(readSource(projectRoot, 'main.js')).toContain(
            "import.meta.glob(['./page/**/*.js', '!./page/**/*.spec.js'], { eager: true })",
        );
        expect(readSource(projectRoot, 'main.js')).toContain("import.meta.glob('./**/@(index|main).js')");
        expect(result.extensions[0].codemods.find((report) => report.codemod.id === 'vite-8-glob')?.manual).toHaveLength(1);
        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/acme-card.vue'))).toBe(false);
    });

    it('converts components to SFCs only when the opt-in codemod is requested', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, write: true, optIns: ['sfc-migration'] });

        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/acme-card.vue'))).toBe(true);
        expect(fs.existsSync(path.join(projectRoot, SOURCE, 'component/acme-card/index.js'))).toBe(true);
    });

    it('applies only the codemods up to the target', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, target: '6.7', write: true });

        expect(readSource(projectRoot, 'page/acme-list/acme-list.html.twig')).toContain('<mt-button');
        expect(readSource(projectRoot, 'page/acme-list/index.js')).toContain('this.$tc(');
    });

    it('does not fail the run when the SFC probe hits a component nobody asked to convert', async () => {
        writeFile(path.join(projectRoot, SOURCE, 'component/acme-broken/index.js'), [
            'export default {',
            '    render() {',
            '        return <div />;',
            '    },',
            '};',
        ]);

        const result = await upgradeExtensions({ projectRoot, administrationRoot });

        expect(result.exitCode).toBe(0);
        expect(result.extensions[0].errors).toEqual([]);
    });

    it('still runs the codemods when one source folder has nothing to lint', async () => {
        writeFile(path.join(projectRoot, PLUGIN, 'src/Snippets/Resources/app/administration/src/snippet/en.json'), [
            '{}',
        ]);
        writePluginsConfig(projectRoot, [
            {
                technicalName: 'AcmeUpgrade',
                basePath: `${PLUGIN}/src`,
                administrationPath: 'Resources/app/administration/src',
            },
            {
                technicalName: 'AcmeSnippets',
                basePath: `${PLUGIN}/src/Snippets`,
                administrationPath: 'Resources/app/administration/src',
            },
        ]);

        const result = await upgradeExtensions({ projectRoot, administrationRoot });
        const [extension] = result.extensions;

        expect(result.exitCode).toBe(0);
        expect(extension.errors).toEqual([]);
        expect(extension.codemods.find((report) => report.codemod.id === 'tc-translation')).toMatchObject({
            changes: 1,
        });
    });

    it('rejects targets and opt-in codemods this Administration does not ship', async () => {
        await expect(upgradeExtensions({ projectRoot, administrationRoot, target: '6.9' })).rejects.toThrow(
            new CodemodSelectionError(
                'This Administration ships codemods up to 6.8. Update Shopware to get the codemods for 6.9, then run this again.',
            ),
        );
        await expect(upgradeExtensions({ projectRoot, administrationRoot, optIns: ['unknown'] })).rejects.toThrow(
            '--with names unknown codemod(s): unknown',
        );
        await expect(
            upgradeExtensions({ projectRoot, administrationRoot, target: '6.7', optIns: ['sfc-migration'] }),
        ).rejects.toThrow('--with=sfc-migration needs --target=6.8 or newer.');
    });
});
