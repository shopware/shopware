/**
 * @sw-package framework
 */
import { inject, provide, type ComponentInternalInstance, type Slot } from 'vue';
import useBlockContext from '../../../../composables/use-block-context';
import useBlockNodeStack from '../shared/use-block-node-stack';
import nativeBlockHostsInjectionKey from './native-block-hosts-injection-key';

const emptySlot: Slot = () => [];

/**
 * @private
 *
 * @component sw-native-block-host
 * @description
 * Extension point injected by the Native → Twig Extension Bridge into components whose template still uses
 * the TwigJS block system. It is never authored by hand: `native-extensions-twig-bridge.ts` registers a
 * synthetic Twig override that replaces a `{% block %}` body with this component, moving the original
 * body — including every legacy Twig override merged into it — into the `#parent` slot.
 *
 * Unlike `sw-block` it does not create Twig shim slots: legacy overrides are already part of the merged
 * `#parent` content, so shimming them again would render them twice.
 *
 * @example injected markup
 * <sw-native-block-host name="sw_product_detail_base" :data="$dataScope">
 *     <template #parent>...original block body...</template>
 * </sw-native-block-host>
 */
export default Shopware.Component.wrapComponentConfig({
    props: {
        name: {
            type: String,
            required: true,
        },
        data: {
            type: Object as PropType<ComponentInternalInstance['proxy']>,
            default: null,
        },
    },
    setup(props, { slots }) {
        const { getBlocks } = useBlockContext();
        const mountedHosts = inject(nativeBlockHostsInjectionKey, []);
        const isNestedHostForSameBlock = mountedHosts.includes(props.name);

        provide(nativeBlockHostsInjectionKey, [
            ...mountedHosts,
            props.name,
        ]);

        const template = useBlockNodeStack(
            () => [
                slots.parent ?? emptySlot,
                // The outermost host for a block name owns its native extensions; an inner host renders
                // the legacy content only, so the extension keeps its single, outermost position.
                ...(isNestedHostForSameBlock ? [] : getBlocks(props.name)),
            ],
            () => props.data,
        );

        return {
            template,
        };
    },
    render() {
        return this.template;
    },
});
