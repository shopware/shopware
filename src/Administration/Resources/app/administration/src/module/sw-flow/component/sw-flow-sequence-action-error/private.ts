import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-sequence-action-error',
    load: () => import('./src/sw-flow-sequence-action-error'),
    public: {
        props: [
            'sequence',
        ],
        slots: [
            'content',
        ],
        blocks: [
            'sw_flow_sequence_action_error',
        ],
        methods: [
            'removeWarning',
        ],
    },
});
