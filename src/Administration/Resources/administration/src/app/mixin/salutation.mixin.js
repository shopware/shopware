import { Mixin, State, Filter } from 'src/core/shopware';

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
