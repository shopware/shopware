/**
 * @sw-package framework
 *
 */
import { computed, onBeforeUnmount, provide, ref, watch, type ComponentInternalInstance, type Slot } from 'vue';
import { hasBlockEntries, getBlockEntries } from 'src/core/factory/twig-block-index';
import parentsInjectionKey from './parents-injection-key';
import useBlockContext from '../../../../composables/use-block-context';
import { createShimSlot } from '../shim/create-shim-slot';

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

        // Shim slots are created once in setup() to guarantee a stable VNode type
        // reference across renders. A new object on every render call would cause
        // Vue to unmount + remount ShimContent on every reactive update, destroying
        // input focus. They are NOT registered in the global blockContext so that
        // multiple simultaneous instances of <sw-block name="foo"> each maintain
        // their own isolated shim slots and cannot double-render each other's content.
        const shimSlots: Slot[] =
            props.name && hasBlockEntries(props.name)
                ? getBlockEntries(props.name).map((entry) => createShimSlot(entry, props.name!))
                : [];

        if (process.env.NODE_ENV !== 'production') {
            // `name` is assumed to be static after mount. Dynamically changing it would
            // require re-creating shim slots and re-binding the block context, which is
            // not supported. This watch fires only in development to surface the mistake early.
            watch(
                () => props.name,
                (newVal, oldVal) => {
                    if (oldVal !== undefined && newVal !== oldVal) {
                        console.warn(
                            `[sw-block] The "name" prop changed from "${oldVal}" to "${newVal}" after mount. ` +
                                `This is not supported and will result in stale shim slots and incorrect rendering.`,
                        );
                    }
                },
            );
        }

        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
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
                ...shimSlots,
                ...nativeBlocks,
            ];
            const blocksNodes = blocksAndParent.map((block) => block?.(props.data));

            const lastNode = blocksNodes.pop();
            // Each <sw-block-parent /> calls .pop() exactly once in its own setup()
            // to claim its parent slot. The array must be reset to the current render's
            // ordered list so that each parent instance pops the correct slot — not a
            // stale or accumulated list from a previous render cycle.
            providedParents.value = blocksNodes;
            return lastNode;
        });

        return {
            template,
        };
    },
    render() {
        return this.template;
    },
});
