/**
 * @package admin
 */

const { Mixin, Filter } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Mixin.register('salutation', {
    computed: {
        salutationFilter() {
            return Filter.getByName('salutation');
        },
    },

    methods: {
        salutation(entity, fallbackSnippet = '') {
            return this.salutationFilter(entity, fallbackSnippet);
        },
    },
});
