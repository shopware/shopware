import Picker from 'vanilla-picker';
import template from './sw-color-picker.html.twig';
import './sw-color-picker.scss';

export default {
    name: 'sw-color-picker',
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: ''
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        config: {
            type: Object,
            required: false,
            default() {
                return {
                    popup: 'left',
                    alpha: false,
                    editor: true,
                    editorFormat: 'hex'
                };
            }
        }
    },

    data() {
        return {
            color: '',
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
        fieldClasses() {
            return {
                'is--disabled': !!this.$props.disabled,
                'is--open': !!this.open
            };
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
                parent: this.$el.querySelector('.sw-color-picker__trigger'),
                onClose: this.onClose,
                onOpen: this.onOpen
            });
            this.colorPicker.setOptions(this.config);
            this.setColor(this.value, true);
        },

        destroyedComponent() {
            delete this.colorPicker;
        },

        setColor(value) {
            if (value !== null && value.length) {
                this.colorPicker.setColor(value, true);
                this.color = value;
            }
        },

        onOpen() {
            if (this.disabled) {
                this.colorPicker.hide();
            } else {
                this.open = true;
                this.$emit('sw-color-picker-open');
            }
        },

        onClose(value) {
            this.open = false;
            this.color = value.hex.substring(0, 7);
            this.$emit('input', this.color);
        }
    }
};
