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
    name: 'sw-colorpicker-deprecated',
    load: () => import('./src/sw-colorpicker-deprecated'),
    public: {
        props: [
            'value',
            'colorOutput',
            'alpha',
            'disabled',
            'readonly',
            'colorLabels',
            'zIndex',
        ],
        emits: [
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        computed: [
            'colorValue',
            'integerAlpha',
            'sliderBackground',
            'isColorValid',
            'previewColorValue',
            'selectorBackground',
            'redValue',
            'greenValue',
            'blueValue',
            'rgbValue',
            'hslValue',
            'hexValue',
            'convertedValue',
            'selectorPositionX',
            'selectorPositionY',
            'selectorStyles',
        ],
        methods: [
            'componentBeforeDestroy',
            'debounceEmitColorValue',
            'outsideClick',
            'setOutsideClickEvent',
            'removeOutsideClickEvent',
            'toggleColorPicker',
            'moveSelector',
            'setDragging',
            'removeDragging',
            'setSingleRGBValue',
            'setHslaValues',
            'splitRGBValues',
            'splitHSLValues',
            'convertHSLtoRGB',
            'convertHSLtoHEX',
            'convertHSL',
            'convertRGBtoHSL',
            'convertHEXtoHSL',
            'onClickInput',
            'roundingFloat',
        ],
    },
});
