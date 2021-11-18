import template from './sw-search-more-results.html.twig';
import './sw-search-more-results.scss';

const { Component, Application } = Shopware;

/**
 * @public
 * @description
 * Renders the search result show more based on the item type.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-search-more-results :result="{entity: 'customer', total: 5}" :term="query">
 * </sw-search-more-results>
 */
Component.register('sw-search-more-results', {
    template,

    inject: [
        'searchTypeService',
    ],

    props: {
        result: {
            required: true,
            type: Object,
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
                !this.result ||
                !this.result.entity ||
                !this.searchTypes[this.result.entity] ||
                !this.searchTypes[this.result.entity].listingRoute
            ) {
                const module = this.moduleFactory.getModuleByEntityName(this.result.entity);

                if (module?.manifest?.routes?.index) {
                    return module.manifest.routes.index.name;
                }

                if (module?.manifest?.routes?.list) {
                    return module.manifest.routes.list.name;
                }

                return '';
            }

            return this.searchTypes[this.result.entity].listingRoute;
        },

        searchTypes() {
            return this.searchTypeService.getTypes();
        },

        searchContent() {
            const { total, entity } = this.result;

            return this.$tc(
                'global.sw-search-more-results.labelShowResultsInModuleV2',
                0,
                { count: total, entityName: this.$tc(`global.entities.${entity}`, 0).toLowerCase() },
            );
        },
    },
});
