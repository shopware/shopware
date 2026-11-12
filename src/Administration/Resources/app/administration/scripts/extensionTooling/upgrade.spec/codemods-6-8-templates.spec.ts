/**
 * @sw-package framework
 *
 * Acceptance spec of the 6.8 component and Twig block codemods: the extension files of
 * `codemods-6-8-templates.fixture.ts`, one real dry run and one real `--write` run with the options of the registry.
 * Like `codemods.spec.ts`, it checks per codemod the counts, a few lines it writes and the spots it marks.
 * The rule specs pin the exact output.
 */

import fs from 'fs';
import path from 'path';
import { upgradeExtensions } from '../upgrade';
import type { ExtensionUpgrade } from '../upgrade';
import { cleanupTempProject, writeFile } from '../test-helpers';
import { PLUGIN, SOURCE, commitAll, createUpgradeProject, readSource } from './fixtures';
import { TEMPLATE_CODEMOD_FIXTURE as FIXTURE } from './codemods-6-8-templates.fixture';

jest.setTimeout(120_000);

const ADMINISTRATION = `${PLUGIN}/src/Resources/app/administration`;

interface TemplateCodemodCase {
    /** Registry id of the codemod. */
    id: string;
    behaviour: string;
    /** Lines the codemod writes, by file below `src`. */
    writes: Record<string, string[]>;
    /** The spots it marks with a codemod comment for manual work, as `<file below src>:<line>`. */
    manual: string[];
}

const CASES: TemplateCodemodCase[] = [
    {
        id: 'mt-components-6.8',
        behaviour:
            'replaces sw-loader, sw-skeleton-bar and sw-progress-bar and adds the max-value of 100 that sw-progress-bar had',
        writes: {
            'component/acme-sync-status/acme-sync-status.html.twig': [
                '<mt-loader v-if="isLoading" size="40px" />',
                '<mt-skeleton-bar v-if="!syncStatus" class="acme-sync-status__skeleton" />',
                '<mt-progress-bar :model-value="uploadProgress" :max-value="100" />',
            ],
        },
        manual: [],
    },
    {
        id: 'mt-tabs',
        behaviour: 'converts sw-tabs-item children with routes and errors to items and marks sw-tabs with a v-for item',
        writes: {
            'page/acme-bundle-detail/acme-bundle-detail.html.twig': [
                ':default-item="$route.name"',
                "{ name: 'acme.bundle.detail.base', label: $t('acme-bundle.detail.tabBase'), hasError: hasBaseErrors, onClick: () => $router.push({ name: 'acme.bundle.detail.base' }) },",
                '@new-item-active="onTabChange($event)"',
            ],
        },
        manual: ['page/acme-bundle-detail/acme-bundle-detail.html.twig:15'],
    },
    {
        id: 'sw-text-editor',
        behaviour: 'marks sw-text-editor',
        writes: {},
        manual: ['extension/sw-product-basic-form/sw-product-basic-form.html.twig:4'],
    },
    {
        id: 'removed-form-components',
        behaviour:
            'converts sw-select-number-field and sw-entity-many-to-many-select, marks the rest and overrides of removed components',
        writes: {
            'component/acme-grid-config/acme-grid-config.html.twig': [
                '<mt-select',
                ":options=\"[{ value: 2, label: '2' }, { value: 3, label: '3' }, { value: 4, label: $t('acme-grid.config.optionFourColumns') }]\"",
            ],
            'component/acme-bundle-assignment/acme-bundle-assignment.html.twig': [
                '<sw-entity-multi-select',
                '@update:entity-collection="onChangeProducts"',
            ],
        },
        manual: [
            'component/acme-bundle-assignment/acme-bundle-assignment.html.twig:8',
            'extension/sw-help-sidebar/index.js:2',
            'extension/sw-select-number-field/index.js:2',
            'page/acme-bundle-list/acme-bundle-list.html.twig:5',
        ],
    },
    {
        id: 'order-state-select-props',
        behaviour:
            'replaces the background-style helper of sw-order-general-info with state-name and marks another background-style',
        writes: {
            'extension/sw-order-general-info/sw-order-general-info.html.twig': [
                ':state-name="transaction.stateMachineState.technicalName"',
            ],
        },
        manual: ['extension/sw-order-general-info/sw-order-general-info.html.twig:21'],
    },
    {
        id: 'sw-popover',
        behaviour: 'renames resize-width to match-reference-width and marks the move to mt-floating-ui',
        writes: {
            'component/acme-product-search/acme-product-search.html.twig': [':match-reference-width="true"'],
        },
        manual: ['component/acme-product-search/acme-product-search.html.twig:4'],
    },
    {
        id: 'twig-block-renames',
        behaviour: 'renames a block and its endblock where both names render on 6.7, otherwise marks the 6.8 block',
        writes: {
            'extension/sw-condition-date-range/sw-condition-date-range.html.twig': [
                '{% block sw_condition_date_range_field_to_date %}',
                '{% endblock sw_condition_date_range_field_to_date %}',
            ],
        },
        manual: ['extension/sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig:1'],
    },
    {
        id: 'twig-block-removals',
        behaviour: 'marks a removed block of the legacy sw-tabs branch',
        writes: {},
        manual: ['extension/sw-product-detail/sw-product-detail.html.twig:1'],
    },
];

const CODEMOD_IDS = CASES.map(({ id }) => id);

const lines = (...text: string[]) => `${text.join('\n')}\n`;

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

/** The spots the report lists as manual work, as `<file below src>:<line>`, sorted. */
function manualSpots(extension: ExtensionUpgrade, id: string): string[] {
    return report(extension, id)
        .manual.map(({ file, line }) => `${file.replace('src/Resources/app/administration/src/', '')}:${line}`)
        .sort();
}

describe('scripts/extensionTooling/upgrade 6.8 template codemods', () => {
    let projectRoot: string;
    let administrationRoot: string;
    let dryRun: ExtensionUpgrade;
    let sourcesAfterDryRun: Record<string, string>;
    let written: ExtensionUpgrade;

    const readFixture = () => Object.fromEntries(Object.keys(FIXTURE).map((file) => [file, readSource(projectRoot, file)]));

    beforeAll(async () => {
        ({ projectRoot, administrationRoot } = createUpgradeProject('sw-tooling-upgrade-templates-'));

        Object.entries(FIXTURE).forEach(([file, content]) => writeFile(path.join(projectRoot, SOURCE, file), content));
        writeFile(path.join(projectRoot, ADMINISTRATION, 'vite.config.mts'), [
            "const entries = import.meta.glob('./src/**/!(*.spec).js');",
            '',
            'export default { entries };',
        ]);
        commitAll(path.join(projectRoot, PLUGIN));

        [dryRun] = (await upgradeExtensions({ projectRoot, administrationRoot })).extensions;
        sourcesAfterDryRun = readFixture();
        [written] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        if (written.errors.length > 0) {
            throw new Error(`The upgrade failed: ${written.errors.join('; ')}`);
        }
    });

    afterAll(() => {
        cleanupTempProject(projectRoot);
    });

    it('counts the changes of each codemod in a dry run without writing a file', () => {
        expect(
            Object.fromEntries(
                CODEMOD_IDS.map((id) => [id, { changes: report(dryRun, id).changes, files: report(dryRun, id).files }]),
            ),
        ).toEqual({
            'mt-components-6.8': { changes: 4, files: 1 },
            'mt-tabs': { changes: 3, files: 2 },
            'sw-text-editor': { changes: 1, files: 1 },
            'removed-form-components': { changes: 6, files: 5 },
            'order-state-select-props': { changes: 2, files: 1 },
            'sw-popover': { changes: 2, files: 1 },
            'twig-block-renames': { changes: 2, files: 2 },
            'twig-block-removals': { changes: 1, files: 1 },
        });
        expect(sourcesAfterDryRun).toEqual(
            Object.fromEntries(Object.entries(FIXTURE).map(([file, content]) => [file, lines(...content)])),
        );
    });

    it.each(CASES)('$id: $behaviour', ({ id, writes, manual }) => {
        Object.entries(writes).forEach(([file, snippets]) => {
            const source = readSource(projectRoot, file);

            snippets.forEach((snippet) => expect(source).toContain(snippet));
        });

        expect(manualSpots(written, id)).toEqual(manual);

        // Scripts report the marked line, templates the line of the codemod comment above it
        manual.forEach((spot) => {
            const [file, line] = spot.split(':');
            const marked = readSource(projectRoot, file)
                .split('\n')
                .slice(Math.max(Number(line) - 2, 0), Number(line));

            expect(marked.join('\n')).toContain(`TODO Codemod(6.8, ${id}): `);
        });
    });

    it("also lints the extension's own vite.config", () => {
        // Other codemods may mark the config as well; this test only proves that the runner lints it.
        const config = fs.readFileSync(path.join(projectRoot, ADMINISTRATION, 'vite.config.mts'), 'utf8');

        expect(written.changedFiles).toContain('src/Resources/app/administration/vite.config.mts');
        expect(config).toContain("const entries = import.meta.glob(['./src/**/*.js', '!./src/**/*.spec.js']);");
        expect(config).not.toContain('!(*.spec)');
    });

    it('keeps the content slot of a converted sw-tabs without a codemod comment', () => {
        const source = readSource(projectRoot, 'view/acme-bundle-seo/acme-bundle-seo.html.twig');

        expect(source).toContain('<mt-tabs');
        expect(source).toContain('<template #content="{ active }">');
        expect(source).not.toContain('TODO Codemod');
    });

    // Runs last: it writes a second time into the project the other tests read.
    it('changes nothing on a second run and keeps listing the TODO spots as manual work', async () => {
        commitAll(path.join(projectRoot, PLUGIN));
        const before = readFixture();

        const [second] = (await upgradeExtensions({ projectRoot, administrationRoot, write: true })).extensions;

        expect(readFixture()).toEqual(before);
        expect(Object.fromEntries(CODEMOD_IDS.map((id) => [id, report(second, id).changes]))).toEqual(
            Object.fromEntries(CODEMOD_IDS.map((id) => [id, 0])),
        );
        expect(Object.fromEntries(CODEMOD_IDS.map((id) => [id, manualSpots(second, id)]))).toEqual(
            Object.fromEntries(CODEMOD_IDS.map((id) => [id, manualSpots(written, id)])),
        );
    });
});
