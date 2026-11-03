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
    name: 'sw-snippet-field-edit-modal',
    load: () => import('./src/sw-snippet-field-edit-modal'),
    public: {
        props: [
            'snippets',
            'snippetSets',
            'translationKey',
            'fieldType',
        ],
        emits: [
            'modal-close',
            'save',
        ],
        blocks: [
            'sw_snippet_field_edit_modal',
            'sw_snippet_field_edit_modal_body',
            'sw_snippet_field_edit_modal_name_field',
            'sw_snippet_field_edit_modal_snippet_fields',
            'sw_snippet_field_edit_modal_footer',
            'sw_snippet_field_edit_modal_close_button',
            'sw_snippet_field_edit_modal_footer_button',
        ],
        computed: [
            'modalTitle',
            'currentAuthor',
            'snippetRepository',
            'textField',
            'textArea',
        ],
        methods: [
            'createdComponent',
            'closeModal',
            'getNoPermissionsTooltip',
            'onSave',
        ],
    },
});
