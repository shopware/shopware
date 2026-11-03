import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-text-field-deprecated,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-select-rating',
    extends: 'sw-text-field-deprecated',
    load: () => import('./src/sw-extension-select-rating'),
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
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'prefix',
            'suffix',
            'label',
            'hint',
        ],
        blocks: [
            'sw_text_field',
            'sw_select_rating',
            'sw_select_rating_input',
            'sw_select_rating_input_stars',
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
