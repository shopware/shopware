import { Mixin } from 'src/core/shopware';
import Picker from 'vanilla-picker';
import template from './sw-colorpicker.html.twig';
import './sw-colorpicker.scss';

/**
 * @public
 * @description Color picker input field.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-colorpicker label="Color picker" value="#dd4800"></sw-colorpicker>
 */

export default {
    name: 'sw-colorpicker',
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('sw-form-field')
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        alpha: {
            type: Boolean,
            required: false,
            default: false
        },

        editorFormat: {
            type: String,
            required: false,
            default: 'hex',
            validValues: ['hex', 'hsl', 'rgb'],
            validator(val) {
                return ['hex', 'hsl', 'rgb'].includes(val);
            }
        },

        colorCallback: {
            type: String,
            required: false,
            default: 'hex',
            validValues: ['hex', 'rgbString', 'rgbaString'],
            validator(val) {
                return ['hex', 'rgbString', 'rgbaString'].includes(val);
            }
        },

        disabled: {
            type: Boolean,
            require: false,
            default: false
        }
    },

    data() {
        return {
            color: this.value,
            open: false
        };
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        emptyColor() {
            return !this.color;
        },

        config() {
            return {
                popup: 'left',
                alpha: this.alpha || false,
                editorFormat: this.editorFormat || 'hex',
                colorCallback: this.colorCallback || 'hex'
            };
        }
    },

    watch: {
        value(value) {
            this.setColor(value);
        },

        color() {
            this.resetFormError();
            this.$emit('input', this.color);
        }
    },

    methods: {
        mountedComponent() {
            this.colorPicker = new Picker({
                parent: this.$el,
                onClose: this.onClose,
                onOpen: this.onOpen,
                onChange: this.onChange
            });

            this.colorPicker.setOptions(this.config);
            this.setColor(this.value);
        },

        destroyedComponent() {
            delete this.colorPicker;
        },

        setColor(value) {
            if (value !== null && value.length) {
                try {
                    this.colorPicker.setColor(value, true);
                    this.color = value;
                } catch (e) { /* ignore wrong initial values or on input */ }

                return;
            }

            this.color = '';
        },

        onOpen() {
            if (this.disabled) {
                this.colorPicker.hide();
            } else {
                this.open = true;
                this.$emit('sw-colorpicker-open');
            }
        },

        openPicker() {
            if (!this.disabled) {
                this.colorPicker.show();
            }
        },

        onInput(event) {
            this.resetFormError();
            this.$emit('input', event.target.value);
        },

        onChange(value) {
            this.color = value[this.colorCallback];
        },

        onClose(value) {
            this.open = false;
            this.color = value[this.colorCallback];
            this.$emit('sw-colorpicker-closed');
        }
    }
};
