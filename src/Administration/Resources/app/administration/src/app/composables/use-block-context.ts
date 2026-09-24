/**
 * @sw-package framework
 */
import { shallowReactive, type ComponentInternalInstance, type VNodeArrayChildren } from 'vue';
import { getTwigBlockRecords, type TwigBlockRecord } from 'src/core/factory/twig-block-index';
import { createTwigShimLayer } from '../component/structure/sw-block-override/shim/twig-shim-layer';

/**
 * @private
 */
export type BlockLayerFrame = {
    /** The component whose template declares the `<sw-block name>`. */
    host: ComponentInternalInstance;
    /** Render cache of this layer inside the rendering block (`_cache` of a compiled render function). */
    cache: unknown[];
    /** Position in the rendered stack; 0 is the block's default content. */
    index: number;
    previous: VNodeArrayChildren;
};

/**
 * @private
 */
export type BlockLayer = {
    render: (scope: unknown, frame: BlockLayerFrame) => VNodeArrayChildren;
    /** The component the layer was registered for. Native `<sw-block extends>` layers have none. */
    componentName?: string;
    /** Layers from `Component.extend()` only apply to hosts that are, or extend, `componentName`. */
    scoped?: boolean;
    priority?: number;
    sequence?: number;
    /** Layers of legacy Twig overrides, which register at boot, before any native layer mounts. */
    legacy?: boolean;
};

// Native layers. A reactive Map because `<sw-block extends>` can register after the named block rendered, e.g.
// when both sit in one template. Arrays are replaced, never mutated, so a registration only re-renders blocks of
// that name.
const layersByBlock = shallowReactive(new Map<string, readonly BlockLayer[]>());
const twigLayers = new WeakMap<TwigBlockRecord, BlockLayer>();
let nextSequence = 0;

function getTwigLayer(record: TwigBlockRecord): BlockLayer {
    let layer = twigLayers.get(record);

    if (!layer) {
        const { blockName, componentName, priority, scoped, sequence } = record;
        layer = createTwigShimLayer(blockName, () => record.entry, { componentName, priority, scoped, sequence });
        twigLayers.set(record, layer);
    }

    return layer;
}

/**
 * Adds a layer on top of a block. Adding a layer that is already registered keeps its position and
 * re-renders the blocks showing it.
 */
function addBlockLayer(blockName: string, layer: BlockLayer): void {
    layer.sequence ??= nextSequence++;
    const layers = (layersByBlock.get(blockName) ?? []).filter((entry) => entry !== layer);

    layersByBlock.set(blockName, [...layers, layer]);
}

function removeBlockLayer(blockName: string, layer: BlockLayer): void {
    const layers = (layersByBlock.get(blockName) ?? []).filter((entry) => entry !== layer);

    if (layers.length > 0) {
        layersByBlock.set(blockName, layers);
    } else {
        layersByBlock.delete(blockName);
    }
}

/**
 * Names of the host component and the components it extends, base first.
 */
function getLineage(host: ComponentInternalInstance | null): string[] {
    const lineage: string[] = [];

    for (
        let type = host?.type as { name?: string; extends?: unknown } | undefined;
        type;
        type = type.extends as typeof type
    ) {
        if (type.name) {
            lineage.unshift(type.name);
        }
    }

    return lineage;
}

/**
 * Returns the layers a host renders on top of the block's default content, bottom first: the native layers of
 * this registry and the legacy Twig layers of `twig-block-index.ts`.
 *
 * One ordering rule for both: inheritance depth, then priority, then Twig before native, then registration.
 * The depth of a layer is the position of its component in the host's `extends` chain (unknown components and
 * native layers count as the base), and a component's own `Component.extend()` template comes before the
 * overrides of that component. For Twig layers this is the order in which TwigJS merges the same templates.
 */
function getBlockLayers(blockName: string, host: ComponentInternalInstance | null = null): BlockLayer[] {
    const layers = [...getTwigBlockRecords(blockName).map(getTwigLayer), ...(layersByBlock.get(blockName) ?? [])];

    if (layers.length === 0) {
        return layers;
    }

    const lineage = getLineage(host);
    const rank = (layer: BlockLayer) => Math.max(lineage.indexOf(layer.componentName ?? ''), 0) * 2 + (layer.scoped ? 0 : 1);

    return layers
        .filter((layer) => !layer.scoped || lineage.includes(layer.componentName ?? ''))
        .sort(
            (a, b) =>
                rank(a) - rank(b) ||
                (a.priority ?? 0) - (b.priority ?? 0) ||
                Number(!a.legacy) - Number(!b.legacy) ||
                (a.sequence ?? 0) - (b.sequence ?? 0),
        );
}

/**
 * @private
 */
export default function useBlockContext() {
    return {
        getBlockLayers,
        addBlockLayer,
        removeBlockLayer,
    };
}
