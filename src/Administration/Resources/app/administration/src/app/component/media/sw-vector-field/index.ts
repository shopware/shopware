import template from './sw-vector-field.html.twig';
import './sw-vector-field.scss';

/**
 * @sw-package innovation
 */

export default Shopware.Component.wrapComponentConfig({
    template,

    emits: [
        'update:value',
        'input-change',
        'link-change',
    ],

    inject: ['feature'],

    mixins: [
        Shopware.Mixin.getByName('sw-form-field'),
        Shopware.Mixin.getByName('remove-api-error'),
        Shopware.Mixin.getByName('validation'),
    ],

    props: {
        value: {
            type: Object,
            required: true
        },

        linkable: {
            type: Boolean,
            required: false,
            default: false
        },

        label: {
            type: String,
            required: false,
            default: ''
        },

        description: {
            type: String,
            required: false,
            default: ''
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        step: {
            type: Number,
            required: false,
            default: null
        }
    },

    data() {
        return {
            linked: false,
            currentValue: {
                x: 0,
                y: 0,
                z: 0
            }
        };
    },

    mounted() {
        if (this.linkable && this.value.x === this.value.y && this.value.x === this.value.z) {
            this.linked = true;
            this.$emit('link-change', this.linked);
        }
    },

    watch: {
        value: {
            deep: true,
            immediate: true,
            handler() {
                if (!this.value) return;
                this.currentValue = {
                    x: this.value.x ?? 0,
                    y: this.value.y ?? 0,
                    z: this.value.z ?? 0
                };
            }
        }
    },

    methods: {
        onChange(event: Event, axis: string) {
            this.updateCurrentValue(event, axis);
            this.$emit('update:value', this.currentValue);
        },

        onInput(event: Event, axis: string) {
            if (this.currentValue[axis] == event) {
                return;
            }

            if (this.linked && this.linkable) {
                // Update all values except itself
                for (const key in this.currentValue) {
                    if (key !== axis) {
                        this.currentValue[key] = event;
                    }
                }
            }
            const newValue = { ...this.currentValue };
            newValue[axis] = event;
            this.$emit('input-change', newValue);
        },

        updateCurrentValue(event: Event, axis: string) {
            if (!(this.linked && this.linkable)) {
                this.currentValue[axis] = event;
                return;
            }

            this.currentValue.x = event;
            this.currentValue.y = event;
            this.currentValue.z = event;
        },

        onLinkToggle() {
            if (this.disabled) return;
            if (!this.linkable) return;

            if (!this.linked) {
                this.currentValue.y = this.currentValue.x;
                this.currentValue.z = this.currentValue.x;
                this.$emit('update:value', this.currentValue);
            }
            this.linked = !this.linked;
            this.$emit('link-change', this.linked);
        }
    }
});
