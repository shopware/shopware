/**
 * @sw-package framework
 *
 * Acceptance spec of the 6.8 component and Twig block codemods: the extension files of
 * `codemods-6-8-templates.fixture.ts`, one real `--write` run with the options of the registry,
 * and per codemod the whole file it writes.
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

/** The codemods of this spec, by registry id. */
const CODEMOD_IDS = [
    'mt-components-6.8',
    'mt-tabs',
    'sw-text-editor',
    'removed-form-components',
    'order-state-select-props',
    'sw-popover',
    'twig-block-renames',
    'twig-block-removals',
];

const lines = (...text: string[]) => `${text.join('\n')}\n`;

function report(extension: ExtensionUpgrade, id: string) {
    const found = extension.codemods.find((candidate) => candidate.codemod.id === id);

    if (!found) {
        throw new Error(`No report for ${id}`);
    }

    return found;
}

/** The spots the report lists as manual work, as `<file below src>:<line>`. */
function manualSpots(extension: ExtensionUpgrade, id: string): string[] {
    return report(extension, id).manual.map(
        ({ file, line }) => `${file.replace('src/Resources/app/administration/src/', '')}:${line}`,
    );
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
            'mt-tabs': { changes: 2, files: 1 },
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

    it('mt-components-6.8: replaces sw-loader, sw-skeleton-bar and sw-progress-bar and adds the max-value of 100 that sw-progress-bar had', () => {
        expect(readSource(projectRoot, 'component/acme-sync-status/acme-sync-status.html.twig')).toBe(
            lines(
                '{% block acme_sync_status %}',
                '<div class="acme-sync-status">',
                '    <!-- TODO Codemod: Converted from sw-loader - please check if everything works correctly -->',
                '    <mt-loader v-if="isLoading" size="40px" />',
                '    <template v-else>',
                '        <!-- TODO Codemod: Converted from sw-skeleton-bar - please check if everything works correctly -->',
                '        <mt-skeleton-bar v-if="!syncStatus" class="acme-sync-status__skeleton" />',
                '        <!-- TODO Codemod: Converted from sw-progress-bar - please check if everything works correctly -->',
                '        <mt-progress-bar',
                '            v-else',
                '            class="acme-sync-status__progress"',
                '            :model-value="syncStatus.processed"',
                '            :max-value="syncStatus.total"',
                '        />',
                '        <!-- TODO Codemod: Converted from sw-progress-bar - please check if everything works correctly -->',
                '        <mt-progress-bar :model-value="uploadProgress" :max-value="100" />',
                '    </template>',
                '</div>',
                '{% endblock %}',
            ),
        );
    });

    it('mt-tabs: converts sw-tabs-item children with routes and errors to items and marks sw-tabs with a v-for item', () => {
        expect(readSource(projectRoot, 'page/acme-bundle-detail/acme-bundle-detail.html.twig')).toBe(
            lines(
                '{% block acme_bundle_detail %}',
                '<sw-page class="acme-bundle-detail">',
                '    <template #content>',
                '        <mt-tabs',
                '            position-identifier="acme-bundle-detail"',
                '            small',
                '            :default-item="$route.name"',
                '            :items="[',
                "                { name: 'acme.bundle.detail.base', label: $t('acme-bundle.detail.tabBase'), hasError: hasBaseErrors, onClick: () => $router.push({ name: 'acme.bundle.detail.base' }) },",
                "                { name: 'acme.bundle.detail.seo', label: $t('acme-bundle.detail.tabSeo'), onClick: () => $router.push({ name: 'acme.bundle.detail.seo' }) },",
                '            ]"',
                '            @new-item-active="onTabChange($event)"',
                '        />',
                '',
                '        {# TODO Codemod(6.8, mt-tabs): sw-tabs is deprecated. Replace it with mt-tabs and pass the sw-tabs-item children as :items ({ name, label, hasError, disabled, badge, onClick }); add small, and @new-item-active now receives the item name. Not converted automatically because of v-for on sw-tabs-item. #}',
                '        <sw-tabs position-identifier="acme-bundle-detail-prices">',
                '            <sw-tabs-item v-for="currency in currencies" :key="currency.id" :name="currency.isoCode">',
                '                {{ currency.translated.name }}',
                '            </sw-tabs-item>',
                '        </sw-tabs>',
                '',
                '        <router-view />',
                '    </template>',
                '</sw-page>',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'mt-tabs')).toEqual(['page/acme-bundle-detail/acme-bundle-detail.html.twig:15']);
    });

    it('sw-text-editor: marks sw-text-editor with the prop mapping to mt-text-editor', () => {
        expect(readSource(projectRoot, 'extension/sw-product-basic-form/sw-product-basic-form.html.twig')).toBe(
            lines(
                '{% block sw_product_basic_form_description_field %}',
                '    {% parent %}',
                '',
                '    {# TODO Codemod(6.8, sw-text-editor): sw-text-editor is removed. Use mt-text-editor: v-model:value → v-model, @update:value → @update:model-value, button-config → custom-buttons, allow-inline-data-mapping → createTextEditorDataMappingButton() from Shopware.Component.getComponentHelper() in custom-buttons. sanitize-*, vertical-align and enable-transparent-background have no replacement. #}',
                '    <sw-text-editor',
                '        v-model:value="product.customFields.acme_short_description"',
                '        :label="$t(\'acme-product.basicForm.labelShortDescription\')"',
                '        sanitize-input',
                '    />',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'sw-text-editor')).toEqual([
            'extension/sw-product-basic-form/sw-product-basic-form.html.twig:4',
        ]);
    });

    it('removed-form-components: converts sw-select-number-field with option children to mt-select and marks an override of it', () => {
        expect(readSource(projectRoot, 'component/acme-grid-config/acme-grid-config.html.twig')).toBe(
            lines(
                '{% block acme_grid_config_columns %}',
                '<mt-select',
                '    v-model="element.config.columns.value"',
                '    :label="$t(\'acme-grid.config.labelColumns\')"',
                "    :options=\"[{ value: 2, label: '2' }, { value: 3, label: '3' }, { value: 4, label: $t('acme-grid.config.optionFourColumns') }]\"",
                '/>',
                '{% endblock %}',
            ),
        );
        expect(readSource(projectRoot, 'extension/sw-select-number-field/index.js')).toBe(
            lines(
                '// TODO Codemod(6.8, removed-form-components): sw-select-number-field is removed. Its replacement mt-select is a Meteor component, which can not be overridden; move the customization to the templates that render it.',
                "Shopware.Component.override('sw-select-number-field', {",
                '    methods: {',
                '        onChange(event) {',
                "            this.$super('onChange', event);",
                '',
                '            if (this.currentValue === null) {',
                '                this.currentValue = 1;',
                '            }',
                '        },',
                '    },',
                '});',
            ),
        );
        expect(manualSpots(written, 'removed-form-components')).toContain('extension/sw-select-number-field/index.js:2');
    });

    it('removed-form-components: converts sw-entity-many-to-many-select in local mode and marks the one that saves the association itself', () => {
        expect(readSource(projectRoot, 'component/acme-bundle-assignment/acme-bundle-assignment.html.twig')).toBe(
            lines(
                '{% block acme_bundle_assignment %}',
                '<div class="acme-bundle-assignment">',
                '    <sw-entity-multi-select',
                '        :entity-collection="bundle.products"',
                '        :label="$t(\'acme-bundle.detail.labelProducts\')"',
                '        @update:entity-collection="onChangeProducts"',
                '    />',
                '    {# TODO Codemod(6.8, removed-form-components): sw-entity-many-to-many-select is removed. Use sw-entity-multi-select, which does not save the association itself: handle @update:entity-collection and save the parent entity. #}',
                '    <sw-entity-many-to-many-select',
                '        :entity-collection="bundle.categories"',
                '        :label="$t(\'acme-bundle.detail.labelCategories\')"',
                '    />',
                '</div>',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'removed-form-components')).toContain(
            'component/acme-bundle-assignment/acme-bundle-assignment.html.twig:8',
        );
    });

    it('removed-form-components: marks sw-help-sidebar in templates and overrides of it', () => {
        expect(readSource(projectRoot, 'page/acme-bundle-list/acme-bundle-list.html.twig')).toBe(
            lines(
                '{% block acme_bundle_list %}',
                '<sw-page class="acme-bundle-list">',
                '    <template #content>',
                '        <acme-bundle-grid />',
                '        {# TODO Codemod(6.8, removed-form-components): sw-help-sidebar is removed without replacement. The help center content is part of sw-help-center-v2. #}',
                '        <sw-help-sidebar selector=".acme-bundle-list" />',
                '    </template>',
                '</sw-page>',
                '{% endblock %}',
            ),
        );
        expect(readSource(projectRoot, 'extension/sw-help-sidebar/index.js')).toBe(
            lines(
                '// TODO Codemod(6.8, removed-form-components): sw-help-sidebar is removed. Move this override to sw-help-center-v2, which has the same Twig block names.',
                "Shopware.Component.override('sw-help-sidebar', {",
                '    computed: {',
                '        showShortcutButton() {',
                '            return false;',
                '        },',
                '    },',
                '});',
            ),
        );
        expect(manualSpots(written, 'removed-form-components')).toEqual(
            expect.arrayContaining([
                'extension/sw-help-sidebar/index.js:2',
                'page/acme-bundle-list/acme-bundle-list.html.twig:5',
            ]),
        );
    });

    it('order-state-select-props: replaces the background-style helper of sw-order-general-info with state-name and marks another background-style', () => {
        expect(readSource(projectRoot, 'extension/sw-order-general-info/sw-order-general-info.html.twig')).toBe(
            lines(
                '{% block sw_order_detail_base_general_info_order_states_payment %}',
                '<div',
                '    v-if="transaction"',
                '    class="sw-order-general-info__order-state"',
                '>',
                '    <sw-order-state-select-v2',
                '        class="sw-order-general-info__order-state-payment"',
                '        :transition-options="paymentStateOptions"',
                '        state-type="order_transaction"',
                '        :state-name="transaction.stateMachineState.technicalName"',
                '        :placeholder="transaction.stateMachineState.translated.name"',
                '        @state-select="onStateSelected"',
                '    />',
                '    <acme-payment-hint :transaction="transaction" />',
                '</div>',
                '{% endblock %}',
                '',
                '{% block sw_order_detail_base_general_info_order_states_order %}',
                '    {% parent %}',
                '',
                '    {# TODO Codemod(6.8, order-state-select-props): background-style of sw-order-state-select-v2 has no effect and is removed in 6.8. Pass the technical name of the current state as state-name instead, e.g. :state-name="order.stateMachineState.technicalName". #}',
                '    <sw-order-state-select-v2',
                '        state-type="acme_order_review"',
                '        :background-style="acmeReviewStateStyle"',
                '        :transition-options="acmeReviewStateOptions"',
                '        @state-select="onAcmeReviewStateSelected"',
                '    />',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'order-state-select-props')).toEqual([
            'extension/sw-order-general-info/sw-order-general-info.html.twig:21',
        ]);
    });

    it('sw-popover: renames resize-width to match-reference-width and marks the move to mt-floating-ui', () => {
        expect(readSource(projectRoot, 'component/acme-product-search/acme-product-search.html.twig')).toBe(
            lines(
                '{% block acme_product_search %}',
                '<div class="acme-product-search">',
                '    <acme-search-field v-model:term="term" />',
                '    {# TODO Codemod(6.8, sw-popover): sw-popover is deprecated. Move to mt-floating-ui: v-if → :is-opened, popover-class → class; z-index has no replacement. #}',
                '    <sw-popover',
                '        v-if="showResults"',
                '        :match-reference-width="true"',
                '        :z-index="1100"',
                '        popover-class="acme-product-search__popover"',
                '    >',
                '        <acme-product-search-results :results="results" @select="onSelect" />',
                '    </sw-popover>',
                '</div>',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'sw-popover')).toEqual([
            'component/acme-product-search/acme-product-search.html.twig:4',
        ]);
    });

    it('twig-block-renames: renames a block and its endblock where both names render on 6.7, otherwise marks the 6.8 block', () => {
        expect(readSource(projectRoot, 'extension/sw-condition-date-range/sw-condition-date-range.html.twig')).toBe(
            lines(
                '{% block sw_condition_date_range_field_to_date %}',
                '    {% parent %}',
                '',
                '    <p class="acme-date-range-hint">{{ $t(\'acme-rule.dateRange.hint\') }}</p>',
                '{% endblock sw_condition_date_range_field_to_date %}',
            ),
        );
        expect(readSource(projectRoot, 'extension/sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig')).toBe(
            lines(
                '{# TODO Codemod(6.8, twig-block-renames): Block sw_entity_single_select_base_results_list_result_label of sw-cms-el-config-buy-box is removed. Override sw_cms_element_buy_box_config_mt_product_select_result_label instead (renders with the v6.8.0.0 flag from 6.7.14). #}',
                '{% block sw_entity_single_select_base_results_list_result_label %}',
                '    {% parent %}',
                '    <span v-if="!item.available" class="acme-sold-out">{{ $t(\'acme-cms.soldOut\') }}</span>',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'twig-block-renames')).toEqual([
            'extension/sw-cms-el-config-buy-box/sw-cms-el-config-buy-box.html.twig:1',
        ]);
    });

    it('twig-block-removals: marks a removed block of the legacy sw-tabs branch with the computed that takes the tab', () => {
        expect(readSource(projectRoot, 'extension/sw-product-detail/sw-product-detail.html.twig')).toBe(
            lines(
                '{# TODO Codemod(6.8, twig-block-removals): Block sw_product_detail_content_tabs_reviews is removed with the legacy sw-tabs branch. Add the tab to the productDetailTabs computed instead. #}',
                '{% block sw_product_detail_content_tabs_reviews %}',
                '    {% parent %}',
                '',
                '    <sw-tabs-item',
                '        class="acme-product-detail__tab-bundles"',
                '        :route="{ name: \'acme.product.detail.bundles\', params: { id: $route.params.id } }"',
                '        :title="$t(\'acme-bundle.productTab.title\')"',
                '    >',
                "        {{ $t('acme-bundle.productTab.title') }}",
                '    </sw-tabs-item>',
                '{% endblock %}',
            ),
        );
        expect(manualSpots(written, 'twig-block-removals')).toEqual([
            'extension/sw-product-detail/sw-product-detail.html.twig:1',
        ]);
    });

    it("also lints the extension's own vite.config", () => {
        expect(written.changedFiles).toContain('src/Resources/app/administration/vite.config.mts');
        expect(fs.readFileSync(path.join(projectRoot, ADMINISTRATION, 'vite.config.mts'), 'utf8')).toBe(
            lines(
                "const entries = import.meta.glob(['./src/**/*.js', '!./src/**/*.spec.js']);",
                '',
                'export default { entries };',
            ),
        );
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
