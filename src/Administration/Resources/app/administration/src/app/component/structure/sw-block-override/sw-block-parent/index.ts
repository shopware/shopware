import { defineComponent, inject, provide, type InjectionKey, type PropType, type VNodeArrayChildren } from 'vue';
import reduceToSingleRoot from '../reduce-to-single-root';

/**
 * The rendered layers of the nearest block, and the index of the layer a `<sw-block-parent>` at this position
 * in the tree renders.
 */
type LayerContext = {
    layers: () => VNodeArrayChildren[];
    index: () => number;
};

const layerContextKey: InjectionKey<LayerContext> = Symbol('sw-block-layer');

function provideLayerBelow(context: LayerContext): void {
    provide(layerContextKey, { layers: context.layers, index: () => context.index() - 1 });
}

/**
 * Renders the top layer of a block. Because the layers arrive as a prop, every `<sw-block-parent>` below
 * re-renders when the block renders new layers.
 *
 * @private
 */
export const SwBlockLayers = defineComponent({
    name: 'SwBlockLayers',
    props: {
        layers: {
            type: Array as PropType<VNodeArrayChildren[]>,
            required: true,
        },
    },
    setup(props) {
        const top = () => props.layers.length - 1;

        provideLayerBelow({ layers: () => props.layers, index: top });

        return () => reduceToSingleRoot(props.layers[top()]);
    },
});

/**
 * @sw-package framework
 *
 * @description
 * Renders the layer below the `<sw-block extends>` it is placed in: the block's default content or the previous
 * override. It finds that layer by its position in the tree, so it also works inside `v-if`, `v-for` and
 * content that mounts later.
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    setup() {
        const context = inject(layerContextKey, null);

        if (context) {
            provideLayerBelow(context);
        }

        const index = () => context?.index() ?? -1;

        return {
            renderContent: () => (index() < 0 ? null : reduceToSingleRoot(context!.layers()[index()])),
        };
    },
    // The component factory only registers components with a template or a `render` option.
    render() {
        return this.renderContent();
    },
});
