import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-text-field-deprecated,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-email-field-deprecated',
    extends: 'sw-text-field-deprecated',
    load: () => import('./src/sw-email-field-deprecated'),
    public: {
        props: [
            'value',
            'placeholder',
            'copyable',
            'copyableTooltip',
            'idSuffix',
            'ariaLabel',
        ],
        emits: [
            'inheritance-restore',
            'inheritance-remove',
            'update:value',
        ],
        slots: [
            'prefix',
            'suffix',
            'label',
            'hint',
        ],
        blocks: [
            'sw_text_field',
            'sw_email_field',
            'sw_email_field_prefix',
            'sw_email_field_prefix_inner',
            'sw_email_field_input',
            'sw_email_field_input_inner',
            'sw_email_field_copyable',
            'sw_email_field_copyable_inner',
        ],
        computed: [
            'hasPrefix',
            'hasSuffix',
            'filteredInputAttributes',
        ],
        methods: [
            'onChange',
            'onInput',
            'restoreInheritance',
            'createInputId',
        ],
    },
});
