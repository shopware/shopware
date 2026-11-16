/**
 * @sw-package framework
 *
 * The shape of the data of removed Twig blocks, and the hint that tells what to use instead.
 */

/**
 * What an override of a removed Twig block uses instead:
 * - a block name of the same component: "Override <block> instead.";
 * - `{ use, in }`: "Override <use> in <in> instead.";
 * - `{ extend }`: "… without replacement. Extend <extend> instead.";
 * - `{ tab }`: the tab is an item of the mt-tabs `items` computed now: "Add the tab to the <tab> computed instead.";
 * - `{ hint }`: a sentence of its own;
 * - `null`: "… without replacement.".
 */
export type BlockReplacement =
    | string
    | { use: string; in?: string }
    | { extend: string }
    | { tab?: string; hint?: string }
    | null;

/**
 * The removed blocks of one core component, and the core components that extend it and so render the same blocks.
 */
export type RemovedBlocks = {
    component: string;
    reason: 'tabs' | 'removed' | 'component';
    inherited: string[];
    blocks: Record<string, BlockReplacement>;
};

const group =
    (reason: RemovedBlocks['reason']) =>
    (component: string, blocks: RemovedBlocks['blocks'], inherited: string[] = []): RemovedBlocks => ({
        component,
        reason,
        inherited,
        blocks,
    });

/**
 * Removed with the legacy sw-tabs branch of `feature.isActive('v6.8.0.0')`.
 */
export const legacyTabs = group('tabs');

/**
 * Removed by a `@deprecated tag:v6.8.0` marker.
 */
export const deprecated = group('removed');

/**
 * Removed together with the whole component.
 */
export function removedComponent(component: string, names: string[]): RemovedBlocks {
    return group('component')(component, Object.fromEntries(names.map((name) => [name, null])));
}

/**
 * Words what to use instead of a removed block, for the report and the review comment. `why` is appended to
 * "It is removed", `shared` names the component when other core components use the name as well, and `flag` is the
 * 6.7 version from which the replacement renders with the `v6.8.0.0` flag.
 *
 * @example
 * removalHint({ component: 'sw-category-view', shared: false, replacement: { tab: 'categoryViewTabs' } });
 * // 'It is removed. Add the tab to the categoryViewTabs computed instead.'
 */
export function removalHint({
    component,
    shared,
    why = '',
    replacement,
    flag,
}: {
    component: string;
    shared: boolean;
    why?: string;
    replacement: BlockReplacement;
    flag?: string;
}): string {
    const subject = `It is removed${shared ? ` from ${component}` : ''}${why}`;
    const note = flag ? ` (renders with the v6.8.0.0 flag from ${flag})` : '';
    const target = typeof replacement === 'string' ? { use: replacement } : replacement;

    if (!target) {
        return `${subject} without replacement. Remove or move this customization.`;
    }

    if ('extend' in target) {
        return `${subject} without replacement. Extend ${target.extend} instead.`;
    }

    if ('use' in target) {
        return `${subject}. Override ${target.use}${target.in ? ` in ${target.in}` : ''} instead${note}.`;
    }

    return target.tab
        ? `${subject}. Add the tab to the ${target.tab} computed instead${note}.`
        : `${subject}. ${target.hint ?? ''}`;
}
