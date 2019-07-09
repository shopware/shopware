import { Component, Mixin } from 'src/core/shopware';
import template from './sw-promotion-basic-form.html.twig';
import './sw-promotion-basic-form.scss';

const { mapApiErrors } = Component.getComponentHelper();

Component.register('sw-promotion-basic-form', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        ...mapApiErrors('promotion', ['name', 'validUntil'])
    }
});
