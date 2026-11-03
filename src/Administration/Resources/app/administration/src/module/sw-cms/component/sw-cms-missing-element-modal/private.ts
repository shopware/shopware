import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-missing-element-modal',
    load: () => import('./src/sw-cms-missing-element-modal'),
    public: {
        props: [
            'missingElements',
        ],
        emits: [
            'modal-close',
            'modal-save',
            'modal-dont-remind-change',
        ],
        blocks: [
            'sw_cms_missing_element_modal',
            'sw_cms_missing_element_modal_title',
            'sw_cms_missing_element_modal_subtitle',
            'sw_cms_missing_element_modal_paragraph',
            'sw_cms_missing_element_modal_button_cancel',
            'sw_cms_missing_element_modal_button_save',
        ],
        computed: [
            'element',
            'title',
        ],
        methods: [
            'onClose',
            'onSave',
            'onChangeDontRemindCheckbox',
        ],
    },
});
