const { Mixin, Filter } = Shopware;

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
