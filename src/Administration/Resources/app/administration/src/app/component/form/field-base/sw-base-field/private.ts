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
    name: 'sw-base-field',
    load: () => import('./src/sw-base-field'),
    public: {
        props: [
            'name',
            'label',
            'helpText',
            'hint',
            'isInvalid',
            'aiBadge',
            'error',
            'disabled',
            'required',
            'isInherited',
            'isInheritanceField',
            'disableInheritanceToggle',
        ],
        emits: [
            'base-field-mounted',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'label',
            'sw-field-input',
            'hint',
        ],
        blocks: [
            'sw_base_field',
        ],
        computed: [
            'identification',
            'hasLabel',
            'hasError',
            'hasHint',
            'swFieldClasses',
            'swFieldLabelClasses',
            'showLabel',
        ],
    },
});
