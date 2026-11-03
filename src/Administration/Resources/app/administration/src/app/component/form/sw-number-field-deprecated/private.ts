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
    name: 'sw-number-field-deprecated',
    extends: 'sw-text-field-deprecated',
    load: () => import('./src/sw-number-field-deprecated'),
    public: {
        props: [
            'numberType',
            'step',
            'min',
            'max',
            'value',
            'digits',
            'fillDigits',
            'allowEmpty',
            'numberAlignEnd',
            'ariaLabel',
            'placeholder',
            'copyable',
            'copyableTooltip',
            'idSuffix',
        ],
        emits: [
            'update:value',
            'input-change',
            'inheritance-restore',
            'inheritance-remove',
            'ends-with-decimal-separator',
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
            'realStep',
            'realMinimum',
            'realMaximum',
            'stringRepresentation',
            'hasPrefix',
            'hasSuffix',
            'filteredInputAttributes',
        ],
        methods: [
            'onChange',
            'onInput',
            'increaseNumberByStep',
            'decreaseNumberByStep',
            'computeValue',
            'parseValue',
            'checkBoundaries',
            'getNumberFromString',
            'checkForInteger',
            'restoreInheritance',
            'createInputId',
        ],
    },
});
