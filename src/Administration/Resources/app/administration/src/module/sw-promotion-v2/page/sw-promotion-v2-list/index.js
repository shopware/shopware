import template from './sw-promotion-v2-list.html.twig';
import './sw-promotion-v2-list.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-promotion-v2-list', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        'listing',
    ],

    data() {
        return {
            isLoading: true,
            promotions: null,
            total: 0,
            showDeleteModal: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        promotionRepository() {
            return this.repositoryFactory.create('promotion');
        },

        promotionCriteria() {
            return (new Criteria(this.page, this.limit))
                .setTerm(this.term)
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));
        },

        promotionColumns() {
            return this.getPromotionColumns();
        },

        addButtonTooltip() {
            return {
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('promotion.creator'),
                showOnDisabledElements: true,
                position: 'bottom',
            };
        },
    },

    methods: {
        getList() {
            this.isLoading = true;

            return this.promotionRepository.search(this.promotionCriteria).then((searchResult) => {
                this.isLoading = false;
                this.total = searchResult.total;
                this.promotions = searchResult;

                return this.promotions;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        getPromotionColumns() {
            return [{
                property: 'name',
                label: 'sw-promotion-v2.list.columnName',
                routerLink: 'sw.promotion.v2.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true,
            }, {
                property: 'active',
                label: 'sw-promotion-v2.list.columnActive',
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center',
            }, {
                property: 'validFrom',
                label: 'sw-promotion-v2.list.columnValidFrom',
                inlineEdit: 'date',
                allowResize: true,
                align: 'center',
            }, {
                property: 'validUntil',
                label: 'sw-promotion-v2.list.columnValidUntil',
                inlineEdit: 'date',
                allowResize: true,
                align: 'center',
            }];
        },

        updateTotal({ total }) {
            this.total = total;
        },
    },
});
