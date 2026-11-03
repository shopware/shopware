import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-discard-changes-modal',
    load: () => import('./src/sw-discard-changes-modal'),
    public: {
        emits: [
            'keep-editing',
            'discard-changes',
        ],
        blocks: [
            'sw_discard_changes_modal',
            'sw_discard_changes_modal_text',
            'sw_discard_changes_modal_footer',
            'sw_discard_changes_modal_discard_changes_modal_cancel',
        ],
        methods: [
            'keepEditing',
            'discardChanges',
        ],
    },
});
