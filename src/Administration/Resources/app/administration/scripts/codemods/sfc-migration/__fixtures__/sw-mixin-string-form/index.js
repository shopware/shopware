import template from './sw-mixin-string-form.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: ['salutation'],

    data() {
        return {
            customer: null,
        };
    },

    computed: {
        // The composable does not expose the mixin's `salutationFilter`, but this declaration
        // shadowed the mixin's anyway, so nothing is lost by migrating.
        salutationFilter() {
            return Shopware.Filter.getByName('salutation');
        },
    },

    methods: {
        formatName() {
            return this.salutation(this.customer, 'no name');
        },

        formatRaw() {
            return this.salutationFilter(this.customer, '');
        },
    },
};
