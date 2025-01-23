/**
 * @sw-package framework
 *
 */
import { computed, onBeforeUnmount, provide, ref, type ComponentInternalInstance, type PropType, type Slot } from 'vue';
import parentsInjectionKey from './parents-injection-key';
import useBlockContext from '../../../../composables/use-block-context';

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
Shopware.Component.register('sw-block', {
    compatConfig: Shopware.compatConfig,
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
            addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                return null;
            }

            const blocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...blocks,
            ];
            const blocksNodes = blocksAndParent.map((block) => block?.(props.data));

            // The last block is not parent of any other block, and it is the one that renders all the blocks
            const lastNode = blocksNodes.pop();
            providedParents.value.push(...blocksNodes);
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
