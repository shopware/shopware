import './sw-wizard-page.scss';
import template from './sw-wizard-page.html.twig';

const { Component } = Shopware;

/**
 * See `sw-wizard` for an example.
 *
 * @private
 */
Component.register('sw-wizard-page', {
    template,

    inject: ['feature'],

    props: {
        isActive: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return false;
            },
        },
        title: {
            type: String,
            required: false,
            default() {
                return '';
            },
        },
        position: {
            type: Number,
            required: true,
        },
    },

    data() {
        return {
            isCurrentlyActive: this.isActive,
            modalTitle: this.title,
        };
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            if (this.feature.isActive('VUE3')) {
                this.$parent.$parent.$parent.$emit('page-add', this);
                return;
            }

            this.$parent.$emit('page-add', this);
        },

        destroyedComponent() {
            if (this.feature.isActive('VUE3')) {
                this.$parent.$parent.$parent.$emit('page-remove', this);
                return;
            }

            this.$parent.$emit('page-remove', this);
        },
    },
});
