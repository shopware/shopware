import { inject } from 'vue';
import template from './sw-switch-field-deprecated.html.twig';
import './sw-switch-field-deprecated.scss';

/**
 * @sw-package framework
 *
 * @private
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-switch-field-deprecated v-model="aBooleanProperty" label="Name"></sw-switch-field>
 */
export default {
    template,

    inheritAttrs: false,

    emits: [
        'inheritance-remove',
        'inheritance-restore',
    ],

    props: {
        noMarginTop: {
            type: Boolean,
            required: false,
            default: false,
        },

        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: [
                'small',
                'medium',
                'default',
            ],
            validator(val) {
                return [
                    'small',
                    'medium',
                    'default',
                ].includes(val);
            },
        },
        ariaLabel: {
            type: String,
            required: false,
            default() {
                return inject('ariaLabel', null)?.value;
            },
        },
    },

    computed: {
        swSwitchFieldClasses() {
            return [
                {
                    'sw-field--switch-bordered': this.bordered,
                    'sw-field--switch-padded': this.padded,
                    'sw-field--switch-no-margin-top': this.noMarginTop,
                    ...this.swCheckboxFieldClasses,
                    [this.$attrs.class]: !!this.$attrs.class,
                },
                `sw-field--${this.size}`,
            ];
        },
    },

    methods: {
        onInheritanceRestore(event) {
            this.$emit('inheritance-restore', event);
        },
    },
};
