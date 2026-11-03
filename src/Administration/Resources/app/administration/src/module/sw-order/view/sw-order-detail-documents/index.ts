import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-order-detail-documents',
    load: () => import('./src/sw-order-detail-documents'),
    public: {
        emits: [
            'save-and-reload',
            'update-loading',
        ],
        blocks: [
            'sw_order_detail_documents',
            'sw_order_detail_documents_card',
        ],
        computed: [
            'isLoading',
            'order',
            'versionContext',
            'isEditing',
            'isV68Active',
        ],
        methods: [
            'saveAndReload',
            'onUpdateLoading',
        ],
    },
});
