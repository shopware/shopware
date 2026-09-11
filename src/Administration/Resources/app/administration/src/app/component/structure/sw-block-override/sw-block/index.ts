/**
 * @sw-package framework
 *
 */
import { getLegacyComponentNames } from '../shim/component-lineage';
import {
    computed,
    getCurrentInstance,
    onBeforeUnmount,
    provide,
    defineComponent,
    h,
    cloneVNode,
    isVNode,
    type VNode,
    shallowRef,
    type ComponentInternalInstance,
} from 'vue';
import { subscribeToBlockIndex, getBlockEntries, type BlockEntry } from 'src/core/factory/twig-block-index';
import parentsInjectionKey from './parents-injection-key';
import useBlockContext from '../../../../composables/use-block-context';
import { createShimSlot, getShimOwner, type ShimSlot } from '../shim/create-shim-slot';
import reduceToSingleRoot from '../reduce-to-single-root';
import useLegacyConditionContext from '../shim/legacy-condition-context';

/**
 * @private
 *
 * @component sw-block
 * @description
 * The `sw-block` component is designed to create an extension point where its content can be overridden or
 * extended. It will render the provided content based on the provided block name, using a context-aware approach
 * to retrieve and  apply the appropriate blocks.
 *
 * To make the `sw-block` component to override or extend content of a specific block it is necessary to provide the
 * block name to override and the `extends` attribute. The `sw-block-parent` component is used to render the parent
 * block default content.
 *
 * The prop `data` is used to pass data to the block content. The `$dataScope` is used to pass the entire component
 * scoped data to the block content.
 *
 * @example override
 * <sw-block name="block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </sw-block-extension>
 *
 * <sw-block extends="block-name">
 *     <div>Block content override</div>
 * </sw-block>
 *
 * @example extend
 * <sw-block name="block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </sw-block>
 *
 * <sw-block extends="block-name">
 *     <sw-block-parent>
 *     <div>Block content extension</div>
 * </sw-block>
 *
 * @example extend with multiple blocks
 * <sw-block name="block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </sw-block>
 *
 * <sw-block extends="block-name">
 *     <sw-block-parent>
 *     <div>Block content extension</div>
 * </sw-block>
 *
 * <sw-block extends="block-name">
 *     <sw-block-parent>
 *     <div>Another block content extension</div>
 * </sw-block>
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
            type: Object as PropType<ComponentInternalInstance['proxy']>,
            default: null,
        },
    },
    setup(props, { slots }) {
        const { addBlock, removeBlock, getBlocks } = useBlockContext();
        const { clearLegacyConditionChainsForBlock } = useLegacyConditionContext();
        const instance = getCurrentInstance();

        if (props.extends) {
            // addBlock is a no-op for undefined, so an explicit guard is not needed.
            addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        onBeforeUnmount(() => {
            if (!props.name) {
                return;
            }

            const ownerUid = instance?.parent?.uid;
            clearLegacyConditionChainsForBlock(props.name, ownerUid);
        });

        const indexVersion = shallowRef(0);
        const unsubscribe = subscribeToBlockIndex(() => {
            indexVersion.value += 1;
        });
        const renderers = new Map<BlockEntry, ShimSlot>();
        const shimSlots = computed(() => {
            void indexVersion.value;
            const owner = getShimOwner(props.data) ?? instance?.parent;
            const entries = props.name ? getBlockEntries(props.name, getLegacyComponentNames(owner)) : [];
            for (const [
                entry,
                renderer,
            ] of renderers) {
                if (!entries.includes(entry)) {
                    renderer.dispose();
                    renderers.delete(entry);
                }
            }
            return entries.map((entry) => {
                let renderer = renderers.get(entry);
                if (!renderer) {
                    renderer = createShimSlot(entry, props.name!);
                    renderers.set(entry, renderer);
                }
                return renderer;
            });
        });
        onBeforeUnmount(() => {
            unsubscribe();
            renderers.forEach((renderer) => renderer.dispose());
        });

        const blockNodes = computed(() => {
            if (!props.name) {
                throw new Error('[sw-block] The "name" prop is required when "extends" is not set.');
            }

            // shimSlots come before nativeBlocks so that Twig plugin overrides (registered
            // at boot time) are positioned below native <sw-block extends> overrides
            // (registered at mount time), matching the expected stacking order:
            //   default → shim (legacy plugin) → native (newer plugin or core extension)
            const nativeBlocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...shimSlots.value,
                ...nativeBlocks,
            ];
            return blocksAndParent.map((block) => block?.(props.data) ?? []);
        });
        // Each layer supplies its predecessor without mutating a shared stack.
        const Layer = defineComponent({
            props: { index: { type: Number, required: true } },
            setup(layerProps) {
                provide(parentsInjectionKey, () => [h(Layer, { index: layerProps.index - 1 })]);
                return () => reduceToSingleRoot(blockNodes.value[layerProps.index]?.map(cloneBlockNode));
            },
        });

        const template = computed(() =>
            blockNodes.value.length === 1 ? blockNodes.value[0] : [h(Layer, { index: blockNodes.value.length - 1 })],
        );

        return {
            template,
        };
    },
    render() {
        return reduceToSingleRoot(this.template);
    },
});

function cloneBlockNode(node: VNode): VNode {
    const copy = cloneVNode(node);
    if (Array.isArray(node.children)) {
        copy.children = node.children.map((child) => (isVNode(child) ? cloneBlockNode(child) : child));
    }
    return copy;
}
