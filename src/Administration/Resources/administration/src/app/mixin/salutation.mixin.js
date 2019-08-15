const { Mixin, State, Filter } = Shopware;

Mixin.register('salutation', {
    computed: {
        salutationStore() {
            return State.getStore('salutation');
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
