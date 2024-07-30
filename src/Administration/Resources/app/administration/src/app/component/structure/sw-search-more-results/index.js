import template from './sw-search-more-results.html.twig';
import './sw-search-more-results.scss';

const { Component, Application } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description
 * Renders the search result show more based on the item type.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-search-more-results :result="{ entity: 'customer', total: 5 }" :entity="customer" :term="query">
 * </sw-search-more-results>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-search-more-results', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'searchTypeService',
    ],

    props: {
        entity: {
            required: true,
            type: String,
            default: '',
        },
        term: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        moduleFactory() {
            return Application.getContainer('factory').module || {};
        },

        /**
         * @return {string}
         */
        searchTypeRoute() {
            if (
                !this.entity ||
                !this.searchTypes[this.entity] ||
                !this.searchTypes[this.entity].listingRoute
            ) {
                const module = this.moduleFactory.getModuleByEntityName(this.entity);

                if (module?.manifest?.routes?.index) {
                    return module.manifest.routes.index.name;
                }

                if (module?.manifest?.routes?.list) {
                    return module.manifest.routes.list.name;
                }

                return '';
            }

            return this.searchTypes[this.entity].listingRoute;
        },

        searchTypes() {
            return this.searchTypeService.getTypes();
        },

        searchContent() {
            const entityName = this.$tc(`global.entities.${this.entity}`, 0);

            return this.$tc(
                'global.sw-search-more-results.labelShowResultsInModuleV2',
                0,
                {
                    entityName: entityName,
                    entityNameLower: entityName.toLowerCase(),
                },
            );
        },
    },
});
