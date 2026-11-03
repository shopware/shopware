/*
 * @sw-package inventory
 */

import template from './sw-manufacturer-list.html.twig';
import './sw-manufacturer-list.scss';
import {
    aclService,
    Criteria,
    getAppContext,
    isFeatureActive,
    listingMixin,
    repositoryFactoryService,
} from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    mixins: [listingMixin],

    data() {
        return {
            manufacturers: null,
            isLoading: true,
            sortBy: 'name',
            sortDirection: 'ASC',
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
        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        manufacturerColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    allowResize: true,
                    routerLink: 'sw.manufacturer.detail',
                    label: 'sw-manufacturer.list.columnName',
                    inlineEdit: 'string',
                    primary: true,
                },
                {
                    property: 'link',
                    label: 'sw-manufacturer.list.columnLink',
                    inlineEdit: 'string',
                },
            ];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria(this.page, this.limit);

            manufacturerCriteria.setTerm(this.term);
            manufacturerCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return manufacturerCriteria;
        },

        adminEsEnable() {
            if (!isFeatureActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return getAppContext().adminEsEnable ?? false;
        },
    },

    methods: {
        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        async getList() {
            this.isLoading = true;

            let criteria;
            if (this.adminEsEnable) {
                criteria = this.manufacturerCriteria;
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, this.manufacturerCriteria);
            }

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            return this.manufacturerRepository.search(criteria).then((searchResult) => {
                this.manufacturers = searchResult;
                this.total = searchResult.total;
                this.isLoading = false;
            });
        },

        updateTotal({ total }) {
            this.total = total;
        },
    },
};
