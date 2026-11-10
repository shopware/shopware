/**
 * @sw-package framework
 *
 * The codemods `admin:upgrade-extensions` can run, keyed by the Shopware major
 * whose upgrade they prepare. A run for a target applies every codemod of that
 * major and of all earlier ones, so an extension can skip a major.
 */

export type CodemodScope = 'script' | 'template' | 'both';

/** `fix` rewrites code and may mark what it cannot rewrite; the other action only marks spots with comments. */
export type CodemodAction = 'fix' | 'todo';

interface BaseCodemod {
    /** Stable id used in `--with` and in reports. */
    id: string;
    title: string;
    /** The Shopware major that needs this change, e.g. "6.8". */
    target: string;
    /** Opt-in codemods only run when named via `--with`. */
    optIn: boolean;
    /** Defaults to `fix`. */
    action?: CodemodAction;
    /** Heading `--list` groups the codemods of a major under. */
    group?: string;
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

/** The components `code-mods.js` migrated: the ones whose `mt-*` replacement is a drop-in for extensions. */
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
];

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
        id: 'mt-components-6.8',
        title: 'sw-loader & co. → mt-*',
        target: '6.8',
        optIn: false,
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-components-6-8',
        options: { fix: true, activatedComponents: ['sw-loader', 'sw-skeleton-bar', 'sw-progress-bar'] },
        scope: 'template',
    },
    {
        id: 'mt-tabs',
        title: 'sw-tabs → mt-tabs',
        target: '6.8',
        optIn: false,
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/migrate-sw-tabs',
        scope: 'template',
    },
    {
        id: 'sw-text-editor',
        title: 'sw-text-editor → mt-text-editor',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-sw-text-editor',
        scope: 'both',
    },
    {
        id: 'removed-form-components',
        title: 'Removed form components',
        target: '6.8',
        optIn: false,
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-form-components',
        scope: 'both',
    },
    {
        id: 'order-state-select-props',
        title: 'sw-order-state-select-v2 props',
        target: '6.8',
        optIn: false,
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-order-state-select-v2-styles',
        scope: 'template',
    },
    {
        id: 'sw-popover',
        title: 'sw-popover resize-width',
        target: '6.8',
        optIn: false,
        group: 'Components',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-sw-popover-resize-width',
        scope: 'template',
    },
    {
        id: 'twig-block-renames',
        title: 'Renamed Twig blocks',
        target: '6.8',
        optIn: false,
        group: 'Twig blocks',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-renamed-twig-blocks',
        scope: 'template',
    },
    {
        id: 'twig-block-removals',
        title: 'Removed Twig blocks',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Twig blocks',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-twig-blocks',
        scope: 'template',
    },
    {
        id: 'filter-computeds',
        title: 'dateFilter/assetFilter computeds',
        target: '6.8',
        optIn: false,
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-filter-computeds',
        scope: 'both',
    },
    {
        id: 'order-list-helpers',
        title: 'Order list delivery helpers',
        target: '6.8',
        optIn: false,
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-order-list-delivery-helpers',
        scope: 'both',
    },
    {
        id: 'order-detail-store',
        title: 'Order detail loading/editing',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-order-detail-loading-members',
        scope: 'both',
    },
    {
        id: 'order-promotion-toggle',
        title: 'Automatic promotion toggle',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-order-promotion-toggle',
        scope: 'both',
    },
    {
        id: 'order-unused-components',
        title: 'Unused order components',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-unused-order-components',
        scope: 'both',
    },
    {
        id: 'order-shipping-costs',
        title: 'Shipping cost editing',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-order-detail-shipping-costs',
        scope: 'script',
    },
    {
        id: 'product-states',
        title: 'Product states → productType',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-product-states',
        scope: 'both',
    },
    {
        id: 'system-config-schema',
        title: 'sw-system-config config/getConfig',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-system-config-config',
        scope: 'both',
    },
    {
        id: 'product-advanced-mode',
        title: 'Product detail advanced mode',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-product-advanced-mode',
        scope: 'both',
    },
    {
        id: 'custom-field-set-loaders',
        title: 'Custom-field set loaders',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-custom-field-set-loaders',
        scope: 'script',
    },
    {
        id: 'rule-condition-components',
        title: 'Legacy rule condition components',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-legacy-rule-conditions',
        scope: 'both',
    },
    {
        id: 'agentic-commerce-ui',
        title: 'Agentic Commerce Admin UI',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-agentic-commerce-ui',
        scope: 'both',
    },
    {
        id: 'media-grid-deselect',
        title: 'sw-media-grid outside click',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-media-grid-outside-click',
        scope: 'both',
    },
    {
        id: 'settings-product-promotion-members',
        title: 'Settings/product/promotion members',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-module-members',
        scope: 'both',
    },
    {
        id: 'flag-gated-behaviour',
        title: 'Flag-gated behaviour',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Component members',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-flag-gated-behaviour',
        scope: 'script',
    },
    {
        id: 'service-calls',
        title: 'Removed service methods',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-service-methods',
        scope: 'script',
    },
    {
        id: 'composition-api-private',
        title: 'Private Composition API calls',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-private-composition-api',
        scope: 'script',
    },
    {
        id: 'vuex-state',
        title: 'Vuex Shopware.State → Pinia',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-vuex-state',
        scope: 'both',
    },
    {
        id: 'axios-v1',
        title: 'axios v1 CancelToken',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-axios-cancel-token',
        scope: 'script',
    },
    {
        id: 'vite-8',
        title: 'Vite 8 config and CJS imports',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-vite-8-pitfalls',
        scope: 'script',
    },
    {
        id: 'worker-notification',
        title: 'Shopware.WorkerNotification',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-worker-notification',
        scope: 'script',
    },
    {
        id: 'mail-template-api',
        title: 'Mail template APIs',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-mail-template-api',
        scope: 'both',
    },
    {
        id: 'cms-helpers',
        title: 'CMS page override helpers',
        target: '6.8',
        optIn: false,
        action: 'todo',
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-cms-helpers',
        scope: 'script',
    },
    {
        id: 'module-snippets',
        title: 'Module snippets property',
        target: '6.8',
        optIn: false,
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-module-snippets-property',
        scope: 'script',
    },
    {
        id: 'vite-8-glob',
        title: 'Vite 8 glob patterns',
        target: '6.8',
        optIn: false,
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-import-meta-glob-extglob',
        scope: 'script',
    },
    {
        id: 'tc-translation',
        title: '$tc → $t',
        target: '6.8',
        optIn: false,
        group: 'Global APIs',
        kind: 'eslint',
        rule: 'sw-core-rules/no-tc-translation',
        scope: 'both',
    },
    {
        id: 'asset-paths',
        title: '.png/.jpg images → .webp',
        target: '6.8',
        optIn: false,
        group: 'Assets and snippets',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-image-assets',
        scope: 'both',
    },
    {
        id: 'snippet-keys',
        title: 'Removed snippet keys',
        target: '6.8',
        optIn: false,
        group: 'Assets and snippets',
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-removed-snippet-keys',
        scope: 'both',
    },
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
