/**
 * @package admin
 */

/* @private */
import { defineComponent } from 'vue';

type SalutationFilterEntityType = {
    salutation: {
        id: string,
        salutationKey: string,
        displayName: string
    },
    title: string,
    firstName: string,
    lastName: string,
    [key: string]: unknown
};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default Shopware.Mixin.register('salutation', defineComponent({
    computed: {
        salutationFilter(): (entity: SalutationFilterEntityType, fallbackSnippet: string) => string {
            return Shopware.Filter.getByName('salutation');
        },
    },

    methods: {
        salutation(entity: SalutationFilterEntityType, fallbackSnippet = '') {
            return this.salutationFilter(entity, fallbackSnippet);
        },
    },
}));
