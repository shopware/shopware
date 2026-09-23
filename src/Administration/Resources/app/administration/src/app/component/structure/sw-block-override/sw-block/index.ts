/**
 * @sw-package framework
 */
import {
    getCurrentInstance,
    h,
    onBeforeUnmount,
    onBeforeUpdate,
    watch,
    type ComponentInternalInstance,
    type PropType,
    type VNodeArrayChildren,
} from 'vue';
import useBlockContext, { type BlockLayer } from '../../../../composables/use-block-context';
import { createBlockPass, runBlockPass } from '../shim/legacy-condition-context';
import reduceToSingleRoot from '../reduce-to-single-root';
import { SwBlockLayers } from '../sw-block-parent/index';
import getBlockDataScope from './get-block-data-scope';

const { addBlockLayer, removeBlockLayer, getBlockLayers } = useBlockContext();

/**
 * @private
 *
 * @component sw-block
 * @description
 * `<sw-block name="...">` declares an extension point and renders its default content. `<sw-block extends="...">`
 * registers its default slot as a layer on top of that block and renders nothing where it is placed.
 * `<sw-block-parent />` inside a layer renders the layer below it. Legacy Twig overrides of the block are
 * layers as well, see `twig-block-index.ts`.
 */
export default Shopware.Component.wrapComponentConfig({
    props: {
        name: {
            type: String,
        },
        extends: {
            type: String,
        },
        data: {
            type: Object as PropType<ComponentInternalInstance['proxy'] | Record<string, unknown>>,
            default: null,
        },
    },
    setup(props, { slots }) {
        if (props.extends) {
            const blockName = props.extends;
            const layer: BlockLayer = {
                render: (scope) => slots.default?.(scope) ?? [],
            };

            if (slots.default) {
                addBlockLayer(blockName, layer);
                // Vue swaps `slots.default` without any reactive signal when the scope around this override
                // changes. Re-adding the layer re-renders the blocks that show it.
                onBeforeUpdate(() => addBlockLayer(blockName, layer));
                onBeforeUnmount(() => removeBlockLayer(blockName, layer));
            }

            return { renderContent: () => null };
        }

        const instance = getCurrentInstance()!;
        // `vnode.ctx` (a Vue internal) is the instance whose render created this vnode: the component whose
        // template declares the block. `parent` would be whichever component renders it into a slot.
        const host = (instance.vnode as { ctx?: ComponentInternalInstance | null }).ctx ?? instance.parent ?? instance;
        const pass = createBlockPass();
        const caches = new WeakMap<BlockLayer, unknown[]>();

        if (process.env.NODE_ENV !== 'production') {
            watch(
                () => props.name,
                (newName, oldName) => {
                    console.warn(
                        `[sw-block] The "name" prop changed from "${oldName}" to "${newName}" after mount. ` +
                            `This is not supported and will result in stale shim slots and incorrect rendering.`,
                    );
                },
            );
        }

        const renderContent = () => {
            if (!props.name) {
                throw new Error('[sw-block] The "name" prop is required when "extends" is not set.');
            }

            const scope = props.data ?? getBlockDataScope(host);
            const layers = getBlockLayers(props.name, host);

            return runBlockPass(pass, () => {
                const rendered: VNodeArrayChildren[] = [slots.default?.(scope) ?? []];

                layers.forEach((layer) => {
                    const cache = caches.get(layer) ?? [];
                    caches.set(layer, cache);
                    rendered.push(
                        layer.render(scope, {
                            host,
                            cache,
                            index: rendered.length,
                            previous: rendered[rendered.length - 1],
                        }),
                    );
                });

                return rendered.length === 1 ? reduceToSingleRoot(rendered[0]) : h(SwBlockLayers, { layers: rendered });
            });
        };

        return { renderContent };
    },
    // The component factory only registers components with a template or a `render` option.
    render() {
        return this.renderContent();
    },
});
