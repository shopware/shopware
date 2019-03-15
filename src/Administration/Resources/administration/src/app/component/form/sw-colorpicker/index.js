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
    extendsFrom: 'sw-text-field',
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
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
            validValues: ['hex', 'hsl', 'rgb']
        },

        colorCallback: {
            type: String,
            required: false,
            default: 'hex',
            validValues: ['hex', 'rgbString', 'rgbaString']
        }
    },

    data() {
        return {
            color: '',
            open: false,
            userConfig: {}
        };
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        fieldClasses() {
            return {
                'is--disabled': !!this.$props.disabled,
                'is--open': !!this.open
            };
        },
        emptyColor() {
            return !this.color;
        }
    },

    watch: {
        value(value) {
            this.setColor(value);
        }
    },

    methods: {
        mountedComponent() {
            this.colorPicker = new Picker({
                parent: this.$el.querySelector('.sw-colorpicker__trigger'),
                onClose: this.onClose,
                onOpen: this.onOpen,
                onChange: this.onChange
            });

            this.userConfig = {
                popup: 'left',
                alpha: this.alpha || false,
                editorFormat: this.editorFormat || 'hex',
                colorCallback: this.colorCallback || 'hex'
            };
            this.colorPicker.setOptions(this.userConfig);
            this.setColor(this.value, true);
        },

        destroyedComponent() {
            delete this.colorPicker;
        },

        setColor(value) {
            if (value !== null && value.length) {
                this.colorPicker.setColor(value, true);
                this.color = value;
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
