import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-state-select-v2',
    load: () => import('./src/sw-order-state-select-v2'),
    public: {
        props: [
            'transitionOptions',
            'stateType',
            'stateName',
            'roundedStyle',
            'placeholder',
            'label',
            'backgroundStyle',
            'disabled',
            'small',
        ],
        emits: [
            'state-select',
        ],
        slots: [
            'hint',
        ],
        blocks: [
            'sw_order_state_select_v2',
            'sw_order_state_select_v2_field',
        ],
        computed: [
            'selectStyle',
            'selectPlaceholder',
            'selectable',
            'currentStateOptionId',
            'selectValue',
        ],
        methods: [
            'onStateChangeClicked',
            'onSelectValueChange',
            'getStateVariant',
        ],
    },
});
