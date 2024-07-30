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

    compatConfig: Shopware.compatConfig,

    inject: [
        'feature',
        'swWizardPageAdd',
        'swWizardPageRemove',
    ],

    props: {
        isActive: {
            type: Boolean,
            required: false,
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
            required: false,
            default() {
                return 0;
            },
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

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.$parent.$emit('page-add', this);
            } else {
                this.swWizardPageAdd(this);
            }
        },

        destroyedComponent() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.$parent.$emit('page-remove', this);
            } else {
                this.swWizardPageRemove(this);
            }
        },
    },
});
