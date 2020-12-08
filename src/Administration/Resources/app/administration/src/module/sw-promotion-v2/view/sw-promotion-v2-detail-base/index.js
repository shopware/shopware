import template from './sw-promotion-v2-detail-base.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-promotion-v2-detail-base', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [
        'placeholder'
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        ...mapPropertyErrors('promotion', ['name', 'validUntil'])
    }
});
