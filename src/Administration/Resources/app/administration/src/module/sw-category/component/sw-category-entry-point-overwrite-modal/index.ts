import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-category-entry-point-overwrite-modal',
    load: () => import('./src/sw-category-entry-point-overwrite-modal'),
    public: {
        props: [
            'salesChannels',
        ],
        emits: [
            'cancel',
            'confirm',
        ],
        blocks: [
            'sw_category_entry_point_overwrite_modal',
        ],
        methods: [
            'onCancel',
            'onConfirm',
        ],
    },
});
