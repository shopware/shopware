/**
 * @package admin
 */

/* @private */
export {};

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
Shopware.Mixin.register('salutation', {
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
});
