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
    name: 'sw-text-field-deprecated',
    load: () => import('./src/sw-text-field-deprecated'),
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
