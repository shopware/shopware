/* istanbul ignore file */

/* Vue devtools plugins couldn't be tested well yet; the DOM and tree logic live in block-inspector-dom.ts and block-inspector-tree.ts and are tested there. */
/**
 * @sw-package framework
 * @private
 *
 * "Shopware Extension Blocks" inspector for the Vue devtools.
 *
 * Lists every extension block that is currently in the DOM, grouped by the component that owns it,
 * highlights the selected block in the page and lets the developer pick a block by clicking on it -
 * the block counterpart of the position identifier inspector for apps.
 *
 * The inspector reads the `data-sw-block` markers the template factory and `<sw-block>` render
 * while the block inspector is enabled. Markers are opt-in and need a reload, which the inspector's
 * power action takes care of.
 */

import type { CustomInspectorState } from '@vue/devtools-api';
import type { DevtoolsPluginApi } from '@vue/devtools-api/lib/esm/api/api';
import type { Router } from 'vue-router';
import TemplateFactory from 'src/core/factory/template.factory';
import { getBlockEntries } from 'src/core/factory/twig-block-index';
import {
    getInspectedBlock,
    isBlockInspectorEnabled,
    setBlockInspectorEnabled,
    type InspectedBlock,
} from 'src/core/factory/block-inspector';
import useBlockContext from 'src/app/composables/use-block-context';
import {
    collectMarkedBlocks,
    createBlockOverlay,
    containedBlockTree,
    documentBlockTree,
    enclosingBlockNames,
    findBlockElements,
    hideOverlayOnPageInteraction,
    startBlockPicking,
} from './block-inspector-dom';
import { blockNameFromNodeId, buildBlockTree, pickedNodeId, type BlockPick, type TreeBlock } from './block-inspector-tree';

/**
 * @private
 */
export const BLOCK_INSPECTOR_ID = 'sw-admin-extension-block-inspector';

const DISABLED_NODE_ID = 'disabled';
const TREE_REFRESH_DELAY = 300;

type TemplateOverride = { raw: string | null };

function describeBlock(blockName: string): InspectedBlock {
    return getInspectedBlock(blockName) ?? { name: blockName, component: 'unknown', kind: 'native' };
}

/** Twig overrides of the owning component that redefine the block. */
function countTwigOverrides(block: InspectedBlock): number {
    const overrides = TemplateFactory.getTemplateOverrides(block.component) as TemplateOverride[];
    const blockPattern = new RegExp(`{%\\s*block\\s+${block.name}\\s*%}`);
    const componentOverrides = overrides.filter((override) => blockPattern.test(override.raw ?? '')).length;

    // Legacy overrides aimed at a native block are indexed separately and rendered through shim slots.
    return componentOverrides + getBlockEntries(block.name).length;
}

function countNativeExtensions(blockName: string): number {
    return useBlockContext().getBlocks(blockName).length;
}

function isExtended(block: InspectedBlock): boolean {
    return countTwigOverrides(block) > 0 || countNativeExtensions(block.name) > 0;
}

function twigSnippet(block: InspectedBlock): string {
    return [
        `Shopware.Component.override('${block.component}', {`,
        `    template: \`{% block ${block.name} %}{% parent %}{% endblock %}\`,`,
        '});',
    ].join('\n');
}

function nativeSnippet(block: InspectedBlock): string {
    return [
        `<sw-block extends="${block.name}">`,
        '    <sw-block-parent />',
        '</sw-block>',
    ].join('\n');
}

function collectTreeBlocks(): TreeBlock[] {
    return Array.from(collectMarkedBlocks().keys()).map((blockName) => {
        const block = describeBlock(blockName);

        return { ...block, extended: isExtended(block) };
    });
}

function buildState(blockName: string): CustomInspectorState {
    const block = describeBlock(blockName);
    const elements = findBlockElements(blockName);

    return {
        Block: [
            { key: 'Name', value: block.name },
            { key: 'Component', value: block.component },
            { key: 'Kind', value: block.kind === 'twig' ? 'Twig template block' : 'Native <sw-block>' },
            { key: 'Elements in DOM', value: elements.length },
            {
                key: 'Enclosing blocks',
                // From the element itself, so outer blocks that start on the same element count too.
                value: enclosingBlockNames(elements[0] ?? null).filter((name) => name !== block.name),
            },
        ],
        Extensions: [
            { key: 'Twig overrides', value: countTwigOverrides(block) },
            { key: 'Native <sw-block extends>', value: countNativeExtensions(block.name) },
        ],
        Snippets: [
            { key: 'Twig override', value: twigSnippet(block) },
            { key: 'Native extension', value: nativeSnippet(block) },
        ],
    };
}

/** The mounted root instance carries the router as a global property; anything else has none. */
function routerOf(root: unknown): Router | undefined {
    return (root as { $router?: Router } | null | undefined)?.$router;
}

/**
 * Whether a setup has already run. See the guard in `setupBlockInspector`.
 */
let isSetUp = false;

/**
 * Adds the block inspector to the Shopware devtools plugin. `root` is the mounted root instance
 * the plugin was registered with; its router drives the rebuild of the tree on route changes.
 *
 * Runs once per page. The devtools call a plugin's setup function more than once - switching off
 * their high performance mode on connect replays the registration - and every run used to build its
 * own overlay and its own pick state. The devtools then called every registered tree handler with
 * the same payload, so the run that had not seen the pick overwrote the tree of the run that had,
 * and a picked block never reached the panel. Two frames were drawn on the page for the same reason.
 *
 * @private
 */
export default function setupBlockInspector(api: DevtoolsPluginApi<unknown>, root?: unknown): void {
    if (isSetUp) {
        return;
    }

    isSetUp = true;

    const overlay = createBlockOverlay();
    let stopPicking: (() => void) | null = null;
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    let generation = 0;
    let pick: BlockPick | null = null;

    // Rebuilds the tree from the DOM once it has settled; bursts of changes collapse into one refresh.
    const scheduleTreeRefresh = (): void => {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }

        refreshTimer = setTimeout(() => {
            refreshTimer = null;
            api.sendInspectorTree(BLOCK_INSPECTOR_ID);
            overlay.refresh();
        }, TREE_REFRESH_DELAY);
    };

    // The devtools never report that their panel closed, so a click or Escape in the page removes
    // the highlight instead. While picking, the picker owns the overlay.
    hideOverlayOnPageInteraction(overlay, { unless: () => stopPicking !== null });

    const highlightBlock = (blockName: string): void => {
        overlay.show(blockName, findBlockElements(blockName));
    };

    const pickBlock = (): void => {
        stopPicking?.();
        stopPicking = startBlockPicking({
            onHover(blockName, elements) {
                if (blockName) {
                    overlay.show(blockName, elements);
                } else {
                    overlay.hide();
                }
            },
            onPick(blockName, element) {
                // New generation: every node id changes, so the tree drops its old selection and
                // lands on the picked block, which is sent as the first root node.
                generation += 1;
                pick = { blockName, contained: containedBlockTree(element, blockName) };

                api.sendInspectorTree(BLOCK_INSPECTOR_ID);
                // Devtools v6 select and scroll through this call; v7 ignore it and rely on the tree.
                api.selectInspectorNode(BLOCK_INSPECTOR_ID, pickedNodeId(blockName, generation));
                highlightBlock(blockName);
            },
            onStop() {
                stopPicking = null;
            },
        });
    };

    api.addInspector({
        id: BLOCK_INSPECTOR_ID,
        label: 'Shopware Extension Blocks',
        icon: 'view_quilt',
        treeFilterPlaceholder: 'Filter blocks or components',
        actions: [
            {
                icon: 'power_settings_new',
                tooltip: isBlockInspectorEnabled() ? 'Disable block markers and reload' : 'Enable block markers and reload',
                action: (): void => {
                    setBlockInspectorEnabled(!isBlockInspectorEnabled());
                    window.location.reload();
                },
            },
            {
                icon: 'ads_click',
                tooltip: 'Pick a block in the page (Escape cancels)',
                action: pickBlock,
            },
            {
                icon: 'flash_off',
                tooltip: 'Clear the picked block, show the whole tree again and remove the highlight',
                action: (): void => {
                    stopPicking?.();
                    overlay.hide();
                    pick = null;
                    api.sendInspectorTree(BLOCK_INSPECTOR_ID);
                },
            },
        ],
    });

    api.on.getInspectorTree((payload) => {
        if (payload.inspectorId !== BLOCK_INSPECTOR_ID) {
            return;
        }

        if (!isBlockInspectorEnabled()) {
            payload.rootNodes = [
                {
                    id: DISABLED_NODE_ID,
                    label: 'Block markers are off - use the power action above to enable them and reload',
                },
            ];

            return;
        }

        payload.rootNodes = buildBlockTree(documentBlockTree(), collectTreeBlocks(), {
            filter: payload.filter,
            generation,
            pick,
        });
    });

    api.on.getInspectorState((payload) => {
        if (payload.inspectorId !== BLOCK_INSPECTOR_ID) {
            return;
        }

        const blockName = blockNameFromNodeId(payload.nodeId);

        if (!blockName) {
            overlay.hide();

            return;
        }

        payload.state = buildState(blockName);
        highlightBlock(blockName);
    });

    if (!isBlockInspectorEnabled()) {
        return;
    }

    // A new route means a new page: the picked block and the highlight belong to the old one, and
    // the tree is rebuilt from scratch once the page has rendered.
    routerOf(root)?.afterEach(() => {
        stopPicking?.();
        overlay.hide();
        pick = null;
        scheduleTreeRefresh();
    });

    // Blocks also come and go with conditions inside a page; keep the tree current without a manual refresh.
    const observer = new MutationObserver(scheduleTreeRefresh);

    observer.observe(document.body, { childList: true, subtree: true });
}
