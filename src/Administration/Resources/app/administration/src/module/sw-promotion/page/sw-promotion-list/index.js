import template from './sw-promotion-list.html.twig';
import './sw-promotion-list.scss';
import entityHydrator from '../../helper/promotion-entity-hydrator.helper';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            promotions: null,
            showDeleteModal: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            isLoading: true,
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

        promotionColumns() {
            return this.getPromotionColumns();
        },
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return this.promotionRepository.search(criteria).then((searchResult) => {
                this.total = searchResult.total;
                this.promotions = searchResult;

                this.promotions.forEach((promotion) => {
                    entityHydrator.hydrate(promotion);
                });

                this.isLoading = false;

                return this.promotions;
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        getPromotionColumns() {
            return [{
                property: 'name',
                label: 'sw-promotion.list.columnName',
                routerLink: 'sw.promotion.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true,
            }, {
                property: 'active',
                label: 'sw-promotion.list.columnActive',
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center',
            }, {
                property: 'validFrom',
                label: 'sw-promotion.list.columnValidFrom',
                inlineEdit: 'date',
                allowResize: true,
                align: 'center',
            }, {
                property: 'validUntil',
                label: 'sw-promotion.list.columnValidUntil',
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
