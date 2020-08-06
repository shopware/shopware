const { Mixin, StateDeprecated, Filter } = Shopware;

Mixin.register('salutation', {
    computed: {
        // @deprecated tag:v6.4.0.0
        salutationStore() {
            return StateDeprecated.getStore('salutation');
        },
        salutationFilter() {
            return Filter.getByName('salutation');
        }
    },

    methods: {
        salutation(entity, fallbackSnippet = '') {
            return this.salutationFilter(entity, fallbackSnippet);
        }
    }
});
