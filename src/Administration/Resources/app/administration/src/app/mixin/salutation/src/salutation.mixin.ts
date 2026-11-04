/**
 * @sw-package framework
 */
import { defineComponent, getSalutationFilter, type SalutationFilterEntityType } from '../dependencies';

/**
 * @public
 *
 * Duplicated in `src/app/composables/use-salutation`; change both together.
 */
export default defineComponent({
    computed: {
        salutationFilter(): (entity: SalutationFilterEntityType, fallbackSnippet: string) => string {
            return getSalutationFilter();
        },
    },

    methods: {
        salutation(entity: SalutationFilterEntityType, fallbackSnippet = '') {
            return this.salutationFilter(entity, fallbackSnippet);
        },
    },
});
