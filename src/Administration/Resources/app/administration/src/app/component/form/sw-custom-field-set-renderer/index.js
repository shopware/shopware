import template from './sw-custom-field-set-renderer.html.twig';
import './sw-custom-field-set-renderer.scss';

const { Component, Mixin } = Shopware;

/**
 * @public
 * @status ready
 * @description
 * Renders custom-field sets
 * @example-type code-only
 * @component-example
 */
Component.register('sw-custom-field-set-renderer', {
    template,

    mixins: [
        Mixin.getByName('sw-inline-snippet')
    ],

    // Grant access to some variables to the child form render components
    provide() {
        return {
            getEntity: this.entity,
            getCustomFieldSet: this.set,
            getCustomFieldSetVariant: this.variant
        };
    },

    props: {
        sets: {
            type: Array,
            required: true
        },
        entity: {
            type: Object,
            required: true
        },
        variant: {
            type: String,
            required: false,
            default: 'tabs',
            validValues: ['tabs', 'media-collapse'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['tabs', 'media-collapse'].includes(value);
            }
        },
        disabled: {
            type: Boolean,
            default: false,
            required: false
        },
        isLoading: {
            type: Boolean,
            default: false,
            require: false
        },
        isSaveSuccessful: {
            type: Boolean,
            default: false,
            require: false
        }
    },

    watch: {
        'entity.customFields': {
            handler() {
                this.initializeCustomFields();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeCustomFields();
        },

        initializeCustomFields() {
            if (!this.entity.customFields) {
                this.entity.customFields = {};
            }
        }
    }
});
