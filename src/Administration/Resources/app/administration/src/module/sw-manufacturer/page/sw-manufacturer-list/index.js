/*
 * @sw-package inventory
 */

import template from './sw-manufacturer-list.html.twig';
import './sw-manufacturer-list.scss';
import SwMeteorEntityDataTable from 'src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.vue'; // eslint-disable-line import/extensions
import { applySearchRankingCriteria } from 'src/app/service/search-ranking-criteria.helper';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    components: {
        SwMeteorEntityDataTable,
    },

    inject: [
        'acl',
        'searchRankingService',
    ],

    data() {
        return {
            isLoading: true,
            term: this.$route.query.term ?? '',
            total: 0,
            searchConfigEntity: 'product_manufacturer',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        manufacturerColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-manufacturer.list.columnName',
                    primary: true,
                    clickable: true,
                    renderer: 'text',
                    previewImage: 'mediaId',
                },
                {
                    property: 'link',
                    label: 'sw-manufacturer.list.columnLink',
                },
            ];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria();

            return manufacturerCriteria;
        },

        adminEsEnable() {
            if (!Shopware.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },
    },

    methods: {
        onSearch(value) {
            this.term = value;
        },

        onSearchTermChange(term) {
            this.term = term;
        },

        async transformManufacturerCriteria(criteria) {
            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else {
                const rankedCriteria = await applySearchRankingCriteria({
                    criteria,
                    term: this.term,
                    searchConfigEntity: this.searchConfigEntity,
                    searchRankingService: this.searchRankingService,
                });

                if (!rankedCriteria.searchable) {
                    return null;
                }

                criteria = rankedCriteria.criteria;
            }

            if (this.term) {
                criteria.resetSorting();
            }

            return criteria;
        },

        onTableTotalChange(total) {
            this.total = total;
        },

        onTableLoadingChange(isLoading) {
            this.isLoading = isLoading;
        },

        isValidTerm(term) {
            return this.searchRankingService.isValidTerm(term);
        },
    },
};
