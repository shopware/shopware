/**
 * @sw-package checkout
 */
import template from './sw-promotion-v2-list.html.twig';
import './sw-promotion-v2-list.scss';
import {
    aclService,
    Criteria,
    getApiContext,
    getAppContext,
    getDateFilter,
    isFeatureActive,
    listingMixin,
    notificationMixin,
    repositoryFactoryService,
} from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    mixins: [listingMixin, notificationMixin],

    data() {
        return {
            isLoading: true,
            promotions: null,
            total: 0,
            showDeleteModal: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            searchConfigEntity: 'promotion',
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
            return new Criteria(this.page, this.limit)
                .setTerm(this.term)
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection));
        },

        promotionColumns() {
            return this.getPromotionColumns();
        },

        addButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('promotion.creator'),
                showOnDisabledElements: true,
                position: 'bottom',
            };
        },

        dateFilter() {
            return getDateFilter();
        },

        allowBulkDelete() {
            return !this.selectionArray.some((item) => item.orderCount > 0);
        },

        adminEsEnable() {
            if (!isFeatureActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return getAppContext().adminEsEnable ?? false;
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            let criteria;
            if (this.adminEsEnable) {
                criteria = this.promotionCriteria;
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, this.promotionCriteria);
            }
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }
            return this.promotionRepository.search(criteria).then((searchResult) => {
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
            return [
                {
                    property: 'name',
                    label: 'sw-promotion-v2.list.columnName',
                    routerLink: 'sw.promotion.v2.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'active',
                    label: 'sw-promotion-v2.list.columnActive',
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'validFrom',
                    label: 'sw-promotion-v2.list.columnValidFrom',
                    inlineEdit: 'date',
                    allowResize: true,
                    align: 'left',
                },
                {
                    property: 'validUntil',
                    label: 'sw-promotion-v2.list.columnValidUntil',
                    inlineEdit: 'date',
                    allowResize: true,
                    align: 'left',
                },
            ];
        },

        updateTotal({ total }) {
            this.total = total;
        },

        async onDuplicatePromotion(referencePromotion) {
            this.isLoading = true;

            try {
                const behavior = {
                    overwrites: {
                        name: `${referencePromotion.name} ${this.$t('global.default.copy')}`,
                        code: null,
                        useCodes: false,
                        useIndividualCodes: false,
                        individualCodePattern: '',
                        individualCodes: null,
                        active: false,
                    },
                };
                const clone = await this.promotionRepository.clone(referencePromotion.id, behavior, getApiContext());

                this.$nextTick(() => {
                    this.$router.push({
                        name: 'sw.promotion.v2.detail',
                        params: { id: clone.id },
                    });
                });

                this.createNotificationInfo({
                    message: this.$t('sw-promotion-v2.list.duplicatePromotionInfo'),
                });
            } catch (error) {
                throw new Error(error);
            } finally {
                this.isLoading = false;
            }
        },

        deleteDisabledTooltip(promotion) {
            return {
                showDelay: 300,
                message: this.$t('sw-promotion-v2.list.deleteDisabledToolTip'),
                disabled: promotion.orderCount === 0,
            };
        },
    },
};
