import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './swag-radio.html.twig';
import './swag-radio.scss';

Component.register('swag-radio', {
    template,

    // Helps to get the correct value for the actual administration language - provides the getInlineSnippet()-function
    mixins: ['sw-inline-snippet'],

    // Getting access to the entity, custom field set and custom field set variant
    inject: ['getEntity', 'getCustomFieldSetVariant', 'getCustomFieldSet'],

    props: {
        // Passed from the custom field config
        label: {
            required: false
        },
        // Passed from the custom field config
        options: {
            required: true,
            type: Array
        },
        // Passed from the custom field config
        variant: {
            type: String,
            required: false,
            default: 'circle'
        },
        // Passed from the entity custom fields
        value: {
            required: false
        }
    },

    data() {
        return {
            utilsId: utils.createId()
        };
    },

    computed: {
        id() {
            return `swag-radio--${this.utilsId}`;
        }
    },

    created() {
        // Access to the entity
        console.log('Entity : ', this.getEntity);
    },

    methods: {
        onOptionClicked(option) {
            this.$emit('input', option.id);
        }
    }
});
