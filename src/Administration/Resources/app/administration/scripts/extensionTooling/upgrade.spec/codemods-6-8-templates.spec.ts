/**
 * @sw-package framework
 *
 * The 6.8 component and Twig block codemods end to end: what a dry run counts, what `--write` produces,
 * that a second run changes nothing, and that an extension's own `vite.config.*` is linted too.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';

jest.setTimeout(120_000);

const ADMINISTRATION = `${PLUGIN}/src/Resources/app/administration`;

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

describe('scripts/extensionTooling/upgrade 6.8 template codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;

    beforeEach(() => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-templates-'));

        writeFile(path.join(projectRoot, SOURCE, 'component/acme-loader/acme-loader.html.twig'), [
            '{% block acme_loader %}',
            '<div>',
            '    <sw-loader v-if="isLoading" size="30px" />',
            '    <sw-progress-bar :value="done" />',
            '</div>',
            '{% endblock %}',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'component/acme-tabs/acme-tabs.html.twig'), [
            '{% block acme_tabs %}',
            '<sw-tabs position-identifier="acme-tabs">',
            '    <sw-tabs-item name="general">{{ $t(\'acme.tabs.general\') }}</sw-tabs-item>',
            '</sw-tabs>',
            '{% endblock %}',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'component/acme-editor/acme-editor.html.twig'), [
            '{% block acme_editor %}',
            '<div>',
            '    <sw-text-editor v-model:value="text" />',
            '    <sw-popover :resize-width="true">',
            '        <div />',
            '    </sw-popover>',
            '</div>',
            '{% endblock %}',
        ]);
        writeFile(path.join(projectRoot, SOURCE, 'extension/sw-condition-date-range/sw-condition-date-range.html.twig'), [
            '{% block sw_condiiton_date_range_field_to_date %}',
            '    {% parent %}',
            '{% endblock %}',
        ]);
        writeFile(
            path.join(projectRoot, SOURCE, 'extension/sw-settings-snippet-set-list/sw-settings-snippet-set-list.html.twig'),
            [
                '{% block sw_settings_snippet_set_list_card_title %}',
                '    {% parent %}',
                '{% endblock %}',
            ],
        );
        writeFile(path.join(projectRoot, ADMINISTRATION, 'vite.config.mjs'), [
            "const entries = import.meta.glob('./src/**/!(*.spec).js');",
            '',
            'export default { entries };',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));
    });

    afterEach(() => {
        cleanupTempProject(projectRoot);
    });

    it('counts the component changes and TODOs in a dry run without writing', async () => {
        const before = readSource(projectRoot, 'component/acme-editor/acme-editor.html.twig');
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot });
        const [extension] = extensions;

        expect(report(extension, 'mt-components-6.8').changes).toBe(2);
        expect(report(extension, 'mt-tabs').changes).toBe(1);
        expect(report(extension, 'sw-text-editor').changes).toBe(1);
        expect(report(extension, 'sw-popover').changes).toBeGreaterThanOrEqual(1);
        expect(report(extension, 'twig-block-renames').changes).toBe(1);
        expect(report(extension, 'twig-block-removals').changes).toBe(1);
        expect(readSource(projectRoot, 'component/acme-editor/acme-editor.html.twig')).toBe(before);
    });

    it('writes the migrations and leaves the TODO spots as manual work', async () => {
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        const [extension] = extensions;
        const loader = readSource(projectRoot, 'component/acme-loader/acme-loader.html.twig');
        const tabs = readSource(projectRoot, 'component/acme-tabs/acme-tabs.html.twig');
        const editor = readSource(projectRoot, 'component/acme-editor/acme-editor.html.twig');

        expect(extension.errors).toEqual([]);
        expect(loader).toContain('<mt-loader v-if="isLoading" size="30px" />');
        expect(loader).toContain('<mt-progress-bar :model-value="done" :max-value="100" />');
        expect(tabs).toContain('<mt-tabs');
        expect(tabs).toContain("label: $t('acme.tabs.general')");
        expect(tabs).not.toContain('sw-tabs-item');
        expect(editor).toContain('{# TODO Codemod(6.8, sw-text-editor): sw-text-editor is removed.');
        expect(editor).toContain(':match-reference-width="true"');
        expect(editor).toContain('{# TODO Codemod(6.8, sw-popover): sw-popover is deprecated.');
        expect(report(extension, 'sw-text-editor').manual).toEqual([
            expect.objectContaining({
                file: 'src/Resources/app/administration/src/component/acme-editor/acme-editor.html.twig',
            }),
        ]);
        expect(readSource(projectRoot, 'extension/sw-condition-date-range/sw-condition-date-range.html.twig')).toContain(
            '{% block sw_condition_date_range_field_to_date %}',
        );
        expect(
            readSource(projectRoot, 'extension/sw-settings-snippet-set-list/sw-settings-snippet-set-list.html.twig'),
        ).toMatch(
            /^\{# TODO Codemod\(6\.8, twig-block-removals\): Block sw_settings_snippet_set_list_card_title is removed/,
        );
    });

    it('changes nothing on a second run', async () => {
        await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        commitAll(path.join(projectRoot, PLUGIN));

        const files = [
            'component/acme-loader/acme-loader.html.twig',
            'component/acme-tabs/acme-tabs.html.twig',
            'component/acme-editor/acme-editor.html.twig',
            'extension/sw-condition-date-range/sw-condition-date-range.html.twig',
            'extension/sw-settings-snippet-set-list/sw-settings-snippet-set-list.html.twig',
        ];
        const before = files.map((file) => readSource(projectRoot, file));
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });
        const [extension] = extensions;

        expect(files.map((file) => readSource(projectRoot, file))).toEqual(before);

        for (const id of [
            'mt-components-6.8',
            'mt-tabs',
            'sw-text-editor',
            'sw-popover',
            'twig-block-renames',
            'twig-block-removals',
        ]) {
            expect(report(extension, id).changes).toBe(0);
        }

        expect(report(extension, 'sw-text-editor').manual).toHaveLength(1);
        expect(report(extension, 'sw-popover').manual).toHaveLength(1);
        expect(report(extension, 'twig-block-removals').manual).toHaveLength(1);
    });

    it("also lints the extension's own vite.config", async () => {
        const { extensions } = await upgradeExtensions({ projectRoot, administrationRoot, write: true });

        expect(extensions[0].changedFiles).toContain('src/Resources/app/administration/vite.config.mjs');
        expect(fs.readFileSync(path.join(projectRoot, ADMINISTRATION, 'vite.config.mjs'), 'utf8')).toContain(
            "import.meta.glob(['./src/**/*.js', '!./src/**/*.spec.js'])",
        );
    });
});
