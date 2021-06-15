import template from './sw-search-more-results.html.twig';
import './sw-search-more-results.scss';

const { Component } = Shopware;
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
        /**
         * @return {string}
         */
        searchTypeRoute() {
            if (!this.result ||
                !this.result.entity ||
                !this.searchTypes[this.result.entity] ||
                !this.searchTypes[this.result.entity].listingRoute) {
                return '';
            }

            return this.searchTypes[this.result.entity].listingRoute;
        },

        searchTypes() {
            return this.searchTypeService.getTypes();
        },
    },
});
