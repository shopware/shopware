/**
 * @package checkout
 */
import template from './sw-promotion-v2-list.html.twig';
import './sw-promotion-v2-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

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
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('promotion.creator'),
                showOnDisabledElements: true,
                position: 'bottom',
            };
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.promotionCriteria);
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
                    align: 'center',
                },
                {
                    property: 'validUntil',
                    label: 'sw-promotion-v2.list.columnValidUntil',
                    inlineEdit: 'date',
                    allowResize: true,
                    align: 'center',
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
                        name: `${referencePromotion.name} ${this.$tc('global.default.copy')}`,
                        code: null,
                        useCodes: false,
                        useIndividualCodes: false,
                        individualCodePattern: '',
                        individualCodes: null,
                        active: false,
                    },
                };
                const clone = await this.promotionRepository.clone(referencePromotion.id, behavior, Shopware.Context.api);

                this.$nextTick(() => {
                    this.$router.push({
                        name: 'sw.promotion.v2.detail',
                        params: { id: clone.id },
                    });
                });

                this.createNotificationInfo({
                    message: this.$tc('sw-promotion-v2.list.duplicatePromotionInfo'),
                });
            } catch (error) {
                throw new Error(error);
            } finally {
                this.isLoading = false;
            }
        },
    },
};
