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
 * The components `no-deprecated-components` converts for extensions: the ones `code-mods.js` migrated and
 * the ones 6.8 removes. The deprecation registry decides per component how much the run level converts;
 * `sw-tabs` and `sw-text-editor` only get a review comment, because their replacements take a different API.
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
    'sw-popover',
    'sw-select-number-field',
    'sw-entity-many-to-many-select',
    'sw-text-editor',
    'sw-text-editor-toolbar',
    'sw-text-editor-toolbar-button',
    'sw-text-editor-toolbar-table-button',
    'sw-text-editor-table-toolbar',
    'sw-text-editor-link-menu',
];

/**
 * How much an upgrade may change, see the run levels of the deprecation registry:
 * `safe` only applies fixes that keep the behaviour, `review` also fixes that leave a review comment,
 * `partial` additionally comments every spot that needs manual work, `manual` only comments.
 */
export const UPGRADE_LEVELS = [
    'safe',
    'review',
    'partial',
    'manual',
] as const;

export type UpgradeLevel = (typeof UPGRADE_LEVELS)[number];

/** Rewrites what is safe and leaves a review comment wherever a human has to finish the migration. */
export const DEFAULT_UPGRADE_LEVEL: UpgradeLevel = 'partial';

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
    {
        id: 'api-usage',
        title: 'Removed Administration APIs',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-api-usage',
        scope: 'both',
    },
    {
        id: 'template-blocks',
        title: 'Removed and renamed Twig blocks',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-template-blocks',
        scope: 'template',
    },
    {
        id: 'snippet-keys',
        title: 'Removed snippet keys',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-snippet-keys',
        scope: 'both',
    },
    {
        id: 'assets',
        title: 'PNG and JPG images → WebP',
        target: '6.8',
        optIn: false,
        kind: 'eslint',
        rule: 'sw-deprecation-rules/no-deprecated-assets',
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
