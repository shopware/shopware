import template from './sw-colorpicker.html.twig';
import './sw-colorpicker.scss';

const { Component, Mixin } = Shopware;
const debounce = Shopware.Utils.debounce;

/**
 * @description
 * The color picker field allows you to select a custom color.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-colorpicker
 *      value="#6ed59f"
 *      colorOutput="auto"
 *      :alpha="true"
 *      :disabled="false"
 *      :colorLabels="true"
 *      zIndex="100">
 * </sw-colorpicker>
 */
Component.register('sw-colorpicker', {
    template,

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: '',
        },

        colorOutput: {
            type: String,
            required: false,
            default: 'auto',
            validValues: [
                'auto',
                'hex',
                'hsl',
                'rgb',
            ],
        },

        alpha: {
            type: Boolean,
            required: false,
            default: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        readonly: {
            type: Boolean,
            required: false,
            default: false,
        },

        colorLabels: {
            type: Boolean,
            required: false,
            default: true,
        },

        zIndex: {
            type: [Number, null],
            required: false,
            default: null,
        },
    },

    data() {
        return {
            localValue: this.value,
            visible: false,
            isDragging: false,
            userInput: null,
            luminanceValue: 50,
            saturationValue: 50,
            hueValue: 0,
            alphaValue: 1,
        };
    },

    computed: {
        colorValue: {
            get() {
                return this.localValue;
            },

            set(newColor) {
                this.localValue = newColor;
                this.debounceEmitColorValue();
            },
        },

        integerAlpha: {
            get() {
                return Math.floor(this.alphaValue * 100);
            },

            set(newAlphaValue) {
                this.alphaValue = newAlphaValue / 100;
            },
        },

        sliderBackground() {
            // eslint-disable-next-line max-len
            return `linear-gradient(90deg, hsla(${this.hueValue}, ${this.saturationValue}%, ${this.luminanceValue}%, 0), hsl(${this.hueValue}, ${this.saturationValue}%, ${this.luminanceValue}%)), url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 20 20\' width=\'100%25\' height=\'100%25\'%3E%3Crect width=\'10\' height=\'10\' x=\'00\' y=\'00\' fill=\'%23cdd5db\' /%3E%3Crect width=\'10\' height=\'10\' x=\'10\' y=\'10\' fill=\'%23cdd5db\' /%3E%3C/svg%3E")`;
        },

        isColorValid() {
            return /^rgb/.test(this.colorValue) || /^hsl/.test(this.colorValue)
                || /^#/.test(this.colorValue);
        },

        previewColorValue() {
            if (!this.isColorValid) {
                return 'transparent';
            }

            return this.colorValue;
        },

        selectorBackground() {
            return `hsl(${this.hueValue}, 100%, 50%)`;
        },

        redValue: {
            get() {
                return this.convertHSLtoRGB(
                    this.hueValue,
                    this.saturationValue,
                    this.luminanceValue,
                    this.alphaValue,
                ).red;
            },

            set(newRedValue) {
                this.setSingleRGBValue(newRedValue, 'red');
            },
        },

        greenValue: {
            get() {
                return this.convertHSLtoRGB(
                    this.hueValue,
                    this.saturationValue,
                    this.luminanceValue,
                    this.alphaValue,
                ).green;
            },

            set(newGreenValue) {
                this.setSingleRGBValue(newGreenValue, 'green');
            },
        },

        blueValue: {
            get() {
                return this.convertHSLtoRGB(
                    this.hueValue,
                    this.saturationValue,
                    this.luminanceValue,
                    this.alphaValue,
                ).blue;
            },

            set(newBlueValue) {
                this.setSingleRGBValue(newBlueValue, 'blue');
            },
        },

        rgbValue() {
            return this.convertHSLtoRGB(
                Math.abs(this.hueValue),
                Math.abs(this.saturationValue),
                Math.abs(this.luminanceValue),
                Math.abs(this.alphaValue),
            ).string;
        },

        hslValue() {
            const hue = Math.abs(Math.floor(this.hueValue));
            const saturation = Math.abs(Math.floor(this.saturationValue));
            const luminance = Math.abs(Math.floor(this.luminanceValue));

            if (this.alphaValue !== 1) {
                const alpha = Math.abs(Number(this.alphaValue.toFixed(2)));
                return `hsla(${hue}, ${saturation}%, ${luminance}%, ${alpha})`;
            }

            return `hsl(${hue}, ${saturation}%, ${luminance}%)`;
        },

        hexValue: {
            get() {
                if (this.alphaValue < 1) {
                    return this.convertHSLtoHEX(
                        this.hueValue,
                        this.saturationValue,
                        this.luminanceValue,
                        this.alphaValue,
                    );
                }

                return this.convertHSLtoHEX(this.hueValue, this.saturationValue, this.luminanceValue);
            },

            set(newValue) {
                // checking if the new value is an actual hex value
                const newHexValue = newValue;
                const validHexCharacters = /^#[0-9a-f]{3,8}/i;

                if (!validHexCharacters.test(newHexValue)) {
                    return;
                }

                const hslValue = this.convertHEXtoHSL(newValue);
                if (hslValue === false) {
                    return;
                }

                this.setHslaValues(
                    hslValue.hue,
                    hslValue.saturation,
                    hslValue.luminance,
                    hslValue.alpha || this.alphaValue,
                );
            },
        },

        convertedValue() {
            switch (this.colorOutput) {
                case 'auto': {
                    return this.alphaValue < 1 ? this.rgbValue : this.hexValue;
                }

                case 'rgb': {
                    return this.rgbValue;
                }

                case 'hsl': {
                    return this.hslValue;
                }

                case 'hex':
                default: {
                    return this.hexValue;
                }
            }
        },

        selectorPositionX() {
            const offsetX = 9;
            return `calc(${this.saturationValue}% - ${offsetX}px)`;
        },

        selectorPositionY() {
            const offsetY = 9;
            return `calc(${Math.abs(this.luminanceValue - 100)}% - ${offsetY}px)`;
        },

        selectorStyles() {
            return {
                backgroundColor: this.hslValue,
                top: this.selectorPositionY,
                left: this.selectorPositionX,
            };
        },
    },

    watch: {
        value() {
            this.colorValue = this.value;
        },

        hslValue() {
            this.colorValue = this.convertedValue;
        },

        visible(visibleStatus) {
            if (!visibleStatus) {
                return;
            }

            const color = this.colorValue;

            if (/^#/.test(color)) {
                // if color is a hex value
                const convertedHSLValue = this.convertHEXtoHSL(this.colorValue);

                this.setHslaValues(
                    convertedHSLValue.hue,
                    convertedHSLValue.saturation,
                    convertedHSLValue.luminance,
                    convertedHSLValue.alpha,
                );
            } else if (/^rgb/.test(color)) {
                // if color is a rgb value
                const rgbValues = this.splitRGBValues(this.colorValue);
                const convertedHSLValue = this.convertRGBtoHSL(rgbValues.red, rgbValues.green, rgbValues.blue);

                this.setHslaValues(
                    convertedHSLValue.hue,
                    convertedHSLValue.saturation,
                    convertedHSLValue.luminance,
                    rgbValues.alpha,
                );
            } else if (/^hsl/.test(color)) {
                // if color is an hsl value
                const hslValues = this.splitHSLValues(this.colorValue);

                this.setHslaValues(
                    hslValues.hue,
                    hslValues.saturation,
                    hslValues.luminance,
                    hslValues.alpha,
                );
            }
        },
    },

    beforeDestroy() {
        this.componentBeforeDestroy();
    },

    methods: {
        componentBeforeDestroy() {
            window.removeEventListener('mousedown', this.outsideClick);
        },

        debounceEmitColorValue: debounce(function emitValue() {
            this.$emit('input', this.colorValue);
        }, 50),

        outsideClick(e) {
            if (/^sw-colorpicker__preview/.test(e.target.classList[0])) {
                return;
            }

            const isColorpicker = e.target.closest('.sw-colorpicker__colorpicker');

            if (isColorpicker !== null) {
                return;
            }

            this.visible = false;
            this.removeOutsideClickEvent();
        },

        setOutsideClickEvent() {
            window.addEventListener('mousedown', this.outsideClick);
        },

        removeOutsideClickEvent() {
            window.removeEventListener('mousedown', this.outsideClick);
        },

        toggleColorPicker() {
            if (this.disabled) {
                return;
            }

            this.visible = !this.visible;

            if (this.visible) {
                this.setOutsideClickEvent();

                return;
            }

            this.removeOutsideClickEvent();
        },

        moveSelector(event) {
            if (!this.isDragging) {
                return;
            }

            const colorpickerLocation = this.$refs.colorPicker.getBoundingClientRect();
            const cursorX = event.clientX - colorpickerLocation.left;
            const cursorY = event.clientY - colorpickerLocation.top;

            const xValue = (cursorX / colorpickerLocation.width) * 100;
            let correctedXValue;

            if (xValue > 100) {
                correctedXValue = 100;
            } else if (xValue < 0) {
                correctedXValue = 0;
            } else {
                correctedXValue = xValue;
            }

            const yValue = ((cursorY / colorpickerLocation.height) - 1) * -100;
            let correctedYValue;

            if (yValue > 100) {
                correctedYValue = 100;
            } else if (yValue < 0) {
                correctedYValue = 0;
            } else {
                correctedYValue = yValue;
            }

            this.saturationValue = Math.floor(correctedXValue);
            this.luminanceValue = Math.floor(correctedYValue);
        },

        setDragging(event) {
            document.body.style.userSelect = 'none';
            this.isDragging = true;
            this.moveSelector(event);

            window.addEventListener('mousemove', this.moveSelector, false);
            window.addEventListener('mouseup', this.removeDragging, false);
        },

        removeDragging() {
            document.body.style.userSelect = null;
            this.isDragging = false;

            window.removeEventListener('mousemove', this.moveSelector);
            window.removeEventListener('mouseup', this.removeDragging);
        },

        setSingleRGBValue(newColorValue, type) {
            const validTypes = ['red', 'green', 'blue'];

            if (validTypes.indexOf(type) === -1) {
                return;
            }

            let sanitizedColorValue = null;

            if (newColorValue > 255) {
                sanitizedColorValue = 255;
            } else if (newColorValue < 0) {
                sanitizedColorValue = 0;
            } else {
                sanitizedColorValue = newColorValue;
            }

            const hslValue = this.convertRGBtoHSL(
                type === 'red' ? sanitizedColorValue : this.redValue,
                type === 'green' ? sanitizedColorValue : this.greenValue,
                type === 'blue' ? sanitizedColorValue : this.blueValue,
            );

            this.setHslaValues(hslValue.hue, hslValue.saturation, hslValue.luminance, this.alphaValue);
        },

        setHslaValues(hue, saturation, luminance, alpha) {
            this.hueValue = hue;
            this.luminanceValue = luminance;
            this.saturationValue = saturation;
            this.alphaValue = !alpha ? 1 : alpha;
        },

        splitRGBValues(rgbString) {
            const rgbValues = rgbString.slice(rgbString.indexOf('(') + 1, rgbString.length - 1).split(', ');

            const red = Number(rgbValues[0]);
            const green = Number(rgbValues[1]);
            const blue = Number(rgbValues[2]);

            const returnValue = {
                red,
                green,
                blue,
            };

            if (/a/.test(rgbString)) {
                returnValue.alpha = Number(rgbValues[3]);
            }

            return returnValue;
        },

        splitHSLValues(hslString) {
            const hslValue = hslString.slice(hslString.indexOf('(') + 1, hslString.length - 1).split(', ');

            // Removing the '%' character in string
            const hue = Number(hslValue[0]);
            const saturation = Number(hslValue[1].slice(0, hslValue[1].length - 1));
            const luminance = Number(hslValue[2].slice(0, hslValue[2].length - 1));
            const alpha = hslValue[3] || hslValue[3] === 0 ? Number(hslValue[3]) : undefined;

            const returnValue = {
                hue,
                saturation,
                luminance,
            };

            if (alpha !== undefined) {
                returnValue.alpha = alpha;
            }

            return returnValue;
        },

        convertHSLtoRGB(previousHue, previousSaturation, previousLuminance, previousAlpha) {
            const hsla = {
                hue: previousHue,
                saturation: previousSaturation,
                luminance: previousLuminance,
                alpha: previousAlpha,
            };

            return this.convertHSL('rgb', hsla);
        },

        convertHSLtoHEX(previousHue, previousSaturation, previousLuminance, previousAlpha) {
            const hsla = {
                hue: previousHue,
                saturation: previousSaturation,
                luminance: previousLuminance,
                alpha: previousAlpha,
            };

            return this.convertHSL('hex', hsla);
        },

        convertHSL(mode, color) {
            const validModes = ['hex', 'rgb'];
            if (!validModes.includes(mode)) {
                return {};
            }

            // eslint-disable-next-line prefer-const
            let { hue, saturation, luminance, alpha } = color;

            saturation /= 100;
            luminance /= 100;

            const chroma = (1 - Math.abs(2 * luminance - 1)) * saturation;
            const x = chroma * (1 - Math.abs(((hue / 60) % 2) - 1));
            const m = luminance - chroma / 2;
            let red = 0;
            let green = 0;
            let blue = 0;

            if (hue >= 0 && hue < 60) {
                red = chroma; green = x; blue = 0;
            } else if (hue >= 60 && hue < 120) {
                red = x; green = chroma; blue = 0;
            } else if (hue >= 120 && hue < 180) {
                red = 0; green = chroma; blue = x;
            } else if (hue >= 180 && hue < 240) {
                red = 0; green = x; blue = chroma;
            } else if (hue >= 240 && hue < 300) {
                red = x; green = 0; blue = chroma;
            } else if (hue >= 300 && hue < 361) {
                red = chroma; green = 0; blue = x;
            }

            red = Math.round((red + m) * 255);
            green = Math.round((green + m) * 255);
            blue = Math.round((blue + m) * 255);

            if (mode === 'hex') {
                // convert colors into hex values
                red = red.toString(16);
                green = green.toString(16);
                blue = blue.toString(16);

                // Prepend 0s, if necessary
                if (red.length === 1) {
                    red = `0${red}`;
                }
                if (green.length === 1) {
                    green = `0${green}`;
                }
                if (blue.length === 1) {
                    blue = `0${blue}`;
                }

                if (alpha === undefined) {
                    return `#${red}${green}${blue}`;
                }

                // convert alpha into hex value
                alpha = Math.round(alpha * 255).toString(16);

                if (alpha.length === 1) {
                    alpha = `0${alpha}`;
                }

                return `#${red}${green}${blue}${alpha}`;
            }

            const rgbValue = {
                string: `rgb(${red}, ${green}, ${blue})`,
                red,
                green,
                blue,
            };

            if (alpha !== 1) {
                rgbValue.string = `rgba(${red}, ${green}, ${blue}, ${alpha})`;
                rgbValue.alpha = alpha;
            }

            return rgbValue;
        },

        convertRGBtoHSL(previousRed, previousGreen, previousBlue) {
            let red = previousRed;
            let green = previousGreen;
            let blue = previousBlue;

            if (/^-/.test(red)) {
                red = Math.abs(red);
            }

            if (/^-/.test(blue)) {
                blue = Math.abs(blue);
            }

            if (/^-/.test(green)) {
                green = Math.abs(green);
            }

            // Make r, g, and b fractions of 1
            red /= 255;
            green /= 255;
            blue /= 255;

            // Find greatest and smallest channel values
            const cmin = Math.min(red, green, blue);
            const cmax = Math.max(red, green, blue);
            const delta = cmax - cmin;
            let hue = 0;
            let saturation = 0;
            let luminance = 0;

            // Calculate hue
            // No difference
            if (delta === 0) {
                hue = 0;
            } else if (cmax === red) {
                hue = ((green - blue) / delta) % 6;
            } else if (cmax === green) {
                hue = (blue - red) / delta + 2;
            } else {
                hue = (red - green) / delta + 4;
            }

            hue = Math.round(hue * 60);

            // Make negative hues positive behind 360°
            if (hue < 0) {
                hue += 360;
            }

            // Calculate lightness
            luminance = (cmax + cmin) / 2;

            // Calculate saturation
            saturation = delta === 0 ? 0 : delta / (1 - Math.abs(2 * luminance - 1));

            saturation = +(saturation * 100).toFixed(1);
            luminance = +(luminance * 100).toFixed(1);

            return {
                string: `hsl(${hue},${saturation}%,${luminance}%)`,
                hue,
                saturation,
                luminance,
            };
        },

        convertHEXtoHSL(previousHex) {
            const hex = previousHex;

            // Convert hex to RGB first
            let red = 0;
            let green = 0;
            let blue = 0;
            let alpha;

            if (hex.length !== 5 && hex.length !== 9 && hex.length !== 4 && hex.length !== 7) {
                return false;
            }

            // with the first two if statements, check if hex string has an alpha value
            // then check if hex string is short or long
            if (hex.length === 5) {
                red = `0x${hex[1]}${hex[1]}`;
                green = `0x${hex[2]}${hex[2]}`;
                blue = `0x${hex[3]}${hex[3]}`;
                alpha = `0x${hex[4]}${hex[4]}`;
            } else if (hex.length === 9) {
                red = `0x${hex[1]}${hex[2]}`;
                green = `0x${hex[3]}${hex[4]}`;
                blue = `0x${hex[5]}${hex[6]}`;
                alpha = `0x${hex[7]}${hex[8]}`;
            } else if (hex.length === 4) {
                red = `0x${hex[1]}${hex[1]}`;
                green = `0x${hex[2]}${hex[2]}`;
                blue = `0x${hex[3]}${hex[3]}`;
            } else if (hex.length === 7) {
                red = `0x${hex[1]}${hex[2]}`;
                green = `0x${hex[3]}${hex[4]}`;
                blue = `0x${hex[5]}${hex[6]}`;
            }

            // Then to HSL
            red /= 255;
            green /= 255;
            blue /= 255;

            const cmin = Math.min(red, green, blue);
            const cmax = Math.max(red, green, blue);
            const delta = cmax - cmin;

            let hue = 0;
            let saturation = 0;
            let luminance = 0;

            if (delta === 0) {
                hue = 0;
            } else if (cmax === red) {
                hue = ((green - blue) / delta) % 6;
            } else if (cmax === green) {
                hue = (blue - red) / delta + 2;
            } else {
                hue = (red - green) / delta + 4;
            }

            hue = Math.round(hue * 60);

            if (hue < 0) {
                hue += 360;
            }

            luminance = (cmax + cmin) / 2;
            saturation = delta === 0 ? 0 : delta / (1 - Math.abs(2 * luminance - 1));
            saturation = +(saturation * 100).toFixed(1);
            luminance = +(luminance * 100).toFixed(1);

            const hslValue = {
                string: `hsl(${hue}, ${saturation}%, ${luminance}%)`,
                hue,
                saturation,
                luminance,
            };

            if (alpha !== 1) {
                hslValue.string = `hsla(${hue}, ${saturation}%, ${luminance}, ${alpha}%)`;

                alpha = Number((alpha / 255).toFixed(2));
                hslValue.alpha = alpha;
            }

            return hslValue;
        },

        onClickInput() {
            if (!this.readonly) {
                return;
            }

            this.toggleColorPicker();
        },
    },
});
