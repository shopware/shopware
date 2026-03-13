import template from './sw-vector-field.html.twig';
import './sw-vector-field.scss';

/**
 * @sw-package innovation
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
            required: true,
        },

        linkable: {
            type: Boolean,
            required: false,
            default: false,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        description: {
            type: String,
            required: false,
            default: '',
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        step: {
            type: Number,
            required: false,
            default: null,
        },

        variant: {
            type: String,
            required: false,
            default: 'colored',
            validValues: [
                'neutral',
                'colored',
            ],
            validator(value: string) {
                return [
                    'neutral',
                    'colored',
                ].includes(value);
            },
        },
    },

    computed: {
        classes(): Record<string, boolean> {
            return {
                'sw-vector-field': true,
                [`sw-vector-field--${this.variant}`]: true,
            };
        },
    },

    data() {
        return {
            linked: false,
            currentValue: {
                x: 0,
                y: 0,
                z: 0,
            },
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
                    x: Number(this.value.x) ?? 0,
                    y: Number(this.value.y) ?? 0,
                    z: Number(this.value.z) ?? 0,
                };
            },
        },
    },

    methods: {
        onChange(event: Event, axis: 'x' | 'y' | 'z') {
            this.updateCurrentValue(event, axis);
            this.$emit('update:value', this.currentValue);
        },

        onInput(event: Event, axis: 'x' | 'y' | 'z') {
            if (this.currentValue[axis] === Number(event)) {
                return;
            }

            if (this.linked && this.linkable) {
                (
                    [
                        'x',
                        'y',
                        'z',
                    ] as const
                ).forEach((key) => {
                    if (key !== axis) {
                        this.currentValue[key] = Number(event);
                    }
                });
            }
            const newValue = { ...this.currentValue };
            newValue[axis] = Number(event);
            this.$emit('input-change', newValue);
        },

        updateCurrentValue(event: Event, axis: 'x' | 'y' | 'z') {
            if (!(this.linked && this.linkable)) {
                this.currentValue[axis] = Number(event);
                return;
            }

            this.currentValue.x = Number(event);
            this.currentValue.y = Number(event);
            this.currentValue.z = Number(event);
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
        },
    },
});
