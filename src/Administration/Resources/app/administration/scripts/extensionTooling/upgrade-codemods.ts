/**
 * @sw-package framework
 *
 * The codemods `admin:upgrade-extensions` can run, keyed by the Shopware major
 * whose upgrade they prepare. A run for a target applies every codemod of that
 * major and of all earlier ones, so an extension can skip a major.
 */

export type CodemodScope = 'script' | 'template' | 'both';

interface BaseCodemod {
    /** Stable id used in `--with` and in reports. */
    id: string;
    title: string;
    /** The Shopware major that needs this change, e.g. "6.8". */
    target: string;
    /** Opt-in codemods only run when named via `--with`. */
    optIn: boolean;
}

export interface EslintCodemod extends BaseCodemod {
    kind: 'eslint';
    rule: string;
    options?: Record<string, unknown>;
    scope: CodemodScope;
}

export interface SfcMigrationCodemod extends BaseCodemod {
    kind: 'sfc-migration';
}

export type Codemod = EslintCodemod | SfcMigrationCodemod;

/**
 * The components `code-mods.js` migrated, plus the ones 6.8 removes. Their `mt-*` replacement is a
 * drop-in, or `mt-component-usage` adapts the usage, like the `items` of `mt-tabs`.
 */
const MT_COMPONENTS = [
    'sw-button',
    'sw-alert',
    'sw-colorpicker',
    'sw-text-field',
    'sw-card',
    'sw-email-field',
    'sw-switch-field',
    'sw-textarea-field',
    'sw-datepicker',
    'sw-icon',
    'sw-url-field',
    'sw-select-field',
    'sw-checkbox-field',
    'sw-number-field',
    'sw-password-field',
    'sw-loader',
    'sw-skeleton-bar',
    'sw-progress-bar',
    'sw-tabs',
];

const eslint68 = (id: string, title: string, rule: string, scope: CodemodScope): EslintCodemod => ({
    id,
    title,
    target: '6.8',
    optIn: false,
    kind: 'eslint',
    rule: `sw-deprecation-rules/${rule}`,
    scope,
});

export const CODEMODS: Codemod[] = [
    {
        id: 'compat-conditions',
        title: 'Vue compat conditions',
        target: '6.6',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-compat-conditions',
        scope: 'script',
    },
    {
        id: 'empty-listeners',
        title: 'Empty listeners() methods',
        target: '6.6',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-empty-listeners',
        scope: 'script',
    },
    {
        id: 'explicit-emits',
        title: 'Explicit emits',
        target: '6.6',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-core-rules/require-explicit-emits',
        scope: 'script',
    },
    {
        id: 'mt-components',
        title: 'sw-* components → mt-*',
        target: '6.7',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-components',
        options: { fix: true, activatedComponents: MT_COMPONENTS },
        scope: 'template',
    },
    {
        id: 'mt-component-usage',
        title: 'Deprecated mt-* props and events',
        target: '6.7',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-component-usage',
        scope: 'template',
    },
    {
        id: 'mt-empty-state',
        title: 'sw-empty-state → mt-empty-state',
        target: '6.7',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-sw-empty-state-usage',
        scope: 'template',
    },
    {
        id: 'vite-8-glob',
        title: 'Vite 8 glob patterns',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-import-meta-glob-extglob',
        scope: 'script',
    },
    {
        id: 'tc-translation',
        title: '$tc → $t',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-core-rules/no-tc-translation',
        scope: 'both',
    },
    eslint68('module-snippets', 'Module.register() snippets', 'no-module-snippets', 'script'),
    eslint68('select-components', 'Removed select components', 'no-removed-select-components', 'template'),
    eslint68('date-filter', 'dateFilter computeds', 'no-date-filter-computed', 'both'),
    eslint68('asset-filter', 'assetFilter computeds', 'no-asset-filter-computed', 'both'),
    eslint68('order-list-helpers', 'sw-order-list delivery helpers', 'no-order-list-delivery-helpers', 'both'),
    eslint68('renamed-twig-blocks', 'Renamed Twig blocks', 'no-renamed-twig-blocks', 'template'),
    eslint68('service-methods', 'Removed service methods', 'no-removed-service-methods', 'both'),
    eslint68('order-state-select', 'sw-order-state-select-v2 styles', 'no-order-state-select-styles', 'template'),
    eslint68('snippet-keys-images', 'Removed snippet keys and images', 'no-removed-snippet-keys-and-images', 'both'),
    // The codemods below only annotate each spot with a comment that names the replacement.
    eslint68(
        'composition-extension-api',
        'Composition API extension system',
        'no-private-composition-extension-api',
        'script',
    ),
    eslint68('vuex-state', 'Vuex Shopware.State → Pinia', 'no-vuex-state', 'both'),
    eslint68('removed-twig-blocks', 'Removed Twig blocks', 'no-removed-twig-blocks', 'template'),
    eslint68('text-editor', 'sw-text-editor → mt-text-editor', 'no-sw-text-editor', 'both'),
    eslint68('order-detail-loading', 'Order detail loading and editing', 'no-order-detail-loading-members', 'both'),
    eslint68('product-states', 'Product states → productType', 'no-product-states', 'both'),
    eslint68('system-config-schema', 'sw-system-config config → schema', 'no-system-config-config', 'both'),
    eslint68('axios-cancel-token', 'axios CancelToken → AbortController', 'no-axios-cancel-token', 'script'),
    eslint68('product-advanced-mode', 'Product detail advanced mode', 'no-product-advanced-mode', 'both'),
    eslint68('vite-8-config', 'Vite 8 build config', 'no-vite-7-config', 'script'),
    eslint68('help-sidebar', 'sw-help-sidebar', 'no-sw-help-sidebar', 'both'),
    eslint68('popover', 'sw-popover → mt-floating-ui', 'no-sw-popover', 'both'),
    eslint68('custom-field-set-loaders', 'Custom field set loaders', 'no-custom-field-set-loaders', 'both'),
    eslint68('rule-conditions', 'Removed rule conditions', 'no-removed-rule-conditions', 'both'),
    eslint68('automatic-promotions', 'Automatic promotion toggle', 'no-automatic-promotion-toggle', 'both'),
    eslint68('unused-order-components', 'Unused order components', 'no-unused-order-components', 'both'),
    eslint68('order-shipping-costs', 'sw-order-detail-details shipping', 'no-order-detail-shipping-costs', 'both'),
    eslint68('worker-notification', 'Shopware.WorkerNotification', 'no-worker-notification', 'script'),
    eslint68('agentic-commerce', 'Agentic Commerce UI', 'no-agentic-commerce-ui', 'both'),
    eslint68('mail-template-preview', 'Mail template preview APIs', 'no-mail-template-preview-apis', 'both'),
    eslint68('media-grid-deselect', 'sw-media-grid outside click', 'no-media-grid-outside-click', 'both'),
    eslint68('cms-helpers', 'CMS helpers', 'no-cms-page-helpers', 'both'),
    eslint68(
        'settings-product-promotion',
        'Settings, product, promotion members',
        'no-removed-settings-product-promotion-members',
        'both',
    ),
    eslint68('changed-defaults', 'Changed Administration defaults', 'no-changed-default-behaviour', 'both'),
    {
        id: 'sfc-migration',
        title: 'Options API → SFC',
        target: '6.8',
        optIn: true,
        kind: 'sfc-migration',
    },
];

function compareVersions(left: string, right: string): number {
    const leftParts = left.split('.').map(Number);
    const rightParts = right.split('.').map(Number);

    for (let index = 0; index < Math.max(leftParts.length, rightParts.length); index++) {
        const difference = (leftParts[index] ?? 0) - (rightParts[index] ?? 0);

        if (difference !== 0) {
            return difference;
        }
    }

    return 0;
}

export function availableTargets(codemods: Codemod[] = CODEMODS): string[] {
    return [...new Set(codemods.map((codemod) => codemod.target))].sort(compareVersions);
}

export class CodemodSelectionError extends Error {}

/**
 * Every codemod up to the target, opt-in ones included so a dry run can point them out.
 * Throws a {@link CodemodSelectionError} for a target or opt-in id this Administration does not ship.
 */
export function selectCodemods(target: string, optIns: string[], codemods: Codemod[] = CODEMODS): Codemod[] {
    const targets = availableTargets(codemods);
    const newest = targets[targets.length - 1];

    if (!/^\d+\.\d+$/.test(target)) {
        throw new CodemodSelectionError(`--target must be a Shopware major like "${newest}", got "${target}".`);
    }

    if (compareVersions(target, newest) > 0) {
        throw new CodemodSelectionError(
            `This Administration ships codemods up to ${newest}. ` +
                `Update Shopware to get the codemods for ${target}, then run this again.`,
        );
    }

    if (!targets.includes(target)) {
        throw new CodemodSelectionError(`There are no codemods for ${target}. Available targets: ${targets.join(', ')}.`);
    }

    const optInIds = codemods.filter((codemod) => codemod.optIn).map((codemod) => codemod.id);
    const unknownOptIns = optIns.filter((id) => !optInIds.includes(id));

    if (unknownOptIns.length > 0) {
        throw new CodemodSelectionError(
            `--with names unknown codemod(s): ${unknownOptIns.join(', ')}. Opt-in codemods: ${optInIds.join(', ')}.`,
        );
    }

    const tooNew = codemods.filter((codemod) => optIns.includes(codemod.id) && compareVersions(codemod.target, target) > 0);

    if (tooNew.length > 0) {
        throw new CodemodSelectionError(
            tooNew.map((codemod) => `--with=${codemod.id} needs --target=${codemod.target} or newer.`).join(' '),
        );
    }

    return codemods.filter((codemod) => compareVersions(codemod.target, target) <= 0);
}
