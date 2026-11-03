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
    name: 'sw-order-state-change-modal',
    load: () => import('./src/sw-order-state-change-modal'),
    public: {
        props: [
            'order',
            'isLoading',
            'technicalName',
        ],
        emits: [
            'page-leave',
            'page-leave-confirm',
        ],
        blocks: [
            'sw_order_state_change_modal',
            'sw_order_state_change_modal_attach_documents_component',
        ],
        computed: [
            'modalTitle',
        ],
        methods: [
            'onCancel',
            'onDocsConfirm',
        ],
    },
});
