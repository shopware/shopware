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
    name: 'sw-confirm-field',
    load: () => import('./src/sw-confirm-field'),
    public: {
        props: [
            'value',
            'compact',
            'preventEmptySubmit',
            'required',
            'disabled',
            'error',
        ],
        emits: [
            'remove-error',
            'blur',
            'submit-cancel',
            'input',
        ],
        blocks: [
            'sw_confirm_field',
            'sw_confirm_field_input_field',
            'sw_confirm_field_button_list',
            'sw_confirm_field_cancel_button',
            'sw_field_inline_cancel_submit_button_icon',
            'sw_confirm_field_confirm_button',
            'sw_field_inline_submit_button_icon',
        ],
        computed: [
            'confirmFieldClasses',
        ],
        methods: [
            'removeActionButtons',
            'onStartEditing',
            'onBlurField',
            'cancelSubmit',
            'onCancelFromKey',
            'onCancelSubmit',
            'submitValue',
            'onSubmitFromKey',
            'onSubmitValue',
            'onInput',
        ],
    },
});
