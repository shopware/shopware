/**
 * @package admin
 */
import template from './sw-block-field.html.twig';
import './sw-block-field.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-block-field', {
    template,
    inheritAttrs: false,

    compatConfig: Shopware.compatConfig,

    props: {
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
    },

    data() {
        return {
            hasFocus: false,
        };
    },

    computed: {
        swBlockSize() {
            return `sw-field--${this.size}`;
        },

        swBlockFieldClasses() {
            return [
                {
                    'has--focus': this.hasFocus,
                },
                this.swBlockSize,
            ];
        },

        listeners() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                return this.$listeners;
            }

            return {};
        },
    },

    methods: {
        setFocusClass() {
            this.hasFocus = true;
        },

        removeFocusClass() {
            this.hasFocus = false;
        },
    },
});
