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

    props: {
        isActive: {
            type: Boolean,
            required: false,
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
            this.$parent.$emit('page-add', this);
        },

        destroyedComponent() {
            this.$parent.$emit('page-remove', this);
        },
    },
});
