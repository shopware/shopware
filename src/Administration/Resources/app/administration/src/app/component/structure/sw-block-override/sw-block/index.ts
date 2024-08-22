/**
 * @package admin
 *
 */
import { computed, onBeforeUnmount, provide, ref, type Slot } from 'vue';
import parentsInjectionKey from './parents-injection-key';

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
 * @example override
 * <sw-block name="block-name">
 *     <div>Default content</div>
 * </sw-block-extension>
 *
 * <sw-block extends="block-name">
 *     <div>Block content override</div>
 * </sw-block>
 *
 * @example extend
 * <sw-block name="block-name">
 *     <div>Default content</div>
 * </sw-block>
 *
 * <sw-block extends="block-name">
 *     <sw-block-parent>
 *     <div>Block content extension</div>
 * </sw-block>
 *
 * @example extend with multiple blocks
 * <sw-block name="block-name">
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
            type: Object,
            default: () => {
            },
        },
    },
    setup(props, { slots }) {
        const store = Shopware.Store.get('blockOverrideState');
        if (props.extends) {
            store.addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    store.removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        const providedParents = ref<(ReturnType<Slot>)[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                return null;
            }

            const blocks = store.getBlocks(props.name);
            return blocks?.length
                ? composeBlocks(blocks)
                : slots.default?.();
        });

        return {
            template,
        };

        function composeBlocks(blocks: Slot[]) {
            return blocks.reduce<ReturnType<Slot> | undefined>(
                (parent, block) => {
                    if (parent) {
                        providedParents.value.push(parent);
                    }
                    return block(props.data);
                },
                slots.default?.(),
            );
        }
    },
    render() {
        return this.template;
    },
});

