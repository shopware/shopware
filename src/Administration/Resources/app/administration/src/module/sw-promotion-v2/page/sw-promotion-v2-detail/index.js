import template from './sw-promotion-v2-detail.html.twig';
import errorConfig from './error-config.json';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        'notification',
        'placeholder',
        Mixin.getByName('discard-detail-page-changes')('promotion'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('promotion.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        promotionId: {
            type: String,
            required: false,
            default() {
                return null;
            },
        },
    },

    data() {
        return {
            isLoading: false,
            promotion: null,
            cleanUpIndividualCodes: false,
            cleanUpFixedCode: false,
            showCodeTypeChangeModal: false,
            isSaveSuccessful: false,
            saveCallbacks: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.promotion, 'name');
        },

        promotionRepository() {
            return this.repositoryFactory.create('promotion');
        },

        isCreateMode() {
            return this.$route.name === 'sw.promotion.v2.create.base';
        },

        promotionCriteria() {
            const criteria = (new Criteria(1, 1))
                .addAssociation('discounts.promotionDiscountPrices')
                .addAssociation('discounts.discountRules')
                .addAssociation('salesChannels');

            criteria.getAssociation('discounts')
                .addSorting(Criteria.sort('createdAt', 'ASC'));

            criteria.getAssociation('individualCodes')
                .setLimit(25);

            return criteria;
        },

        tooltipSave() {
            if (!this.acl.can('promotion.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        promotionGroupRepository() {
            return this.repositoryFactory.create('promotion_setgroup');
        },

        ...mapPageErrors(errorConfig),
    },

    watch: {
        promotionId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-promotion-detail__promotion',
                path: 'promotion',
                scope: this,
            });
            this.isLoading = true;

            if (!this.promotionId) {
                // set language to system language
                if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                    Shopware.State.commit('context/resetLanguageToDefault');
                }

                this.promotion = this.promotionRepository.create();
                this.isLoading = false;

                return;
            }

            this.loadEntityData();
        },

        loadEntityData() {
            return this.promotionRepository.get(this.promotionId, Shopware.Context.api, this.promotionCriteria)
                .then((promotion) => {
                    if (promotion === null) {
                        return;
                    }

                    this.promotion = promotion;

                    if (!this.promotion || !this.promotion.discounts || this.promotion.length < 1) {
                        return;
                    }

                    // Needed to enrich the VueX state below
                    this.promotion.hasOrders = (promotion.orderCount !== null) ? promotion.orderCount > 0 : false;

                    Shopware.State.commit('swPromotionDetail/setPromotion', this.promotion);
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            if (!this.promotionId) {
                this.createPromotion();

                return;
            }

            if (![this.cleanUpIndividualCodes, this.cleanUpFixedCode].some(check => check)) {
                this.savePromotion();

                return;
            }

            this.showCodeTypeChangeModal = true;
        },

        onConfirmSave() {
            this.onCloseCodeTypeChangeModal();
            this.savePromotion();
        },

        onCloseCodeTypeChangeModal() {
            this.showCodeTypeChangeModal = false;
        },

        createPromotion() {
            return this.savePromotion().then(() => {
                this.$router.push({ name: 'sw.promotion.v2.detail', params: { id: this.promotion.id } });
            });
        },

        savePromotion() {
            this.isLoading = true;

            if (this.cleanUpIndividualCodes === true) {
                this.promotion.individualCodes = this.promotion.individualCodes.filter(() => false);
            }

            if (this.cleanUpFixedCode === true) {
                this.promotion.code = '';
            }

            if (this.promotion.discounts) {
                this.promotion.discounts.forEach((discount) => {
                    if (discount.type === 'free') {
                        Object.assign(discount, {
                            type: 'percentage',
                            value: 100,
                            applierKey: 'SELECT',
                        });
                    }
                });
            }

            return this.promotionRepository.save(this.promotion)
                .then(() => {
                    this.loadEntityData();
                    return this.savePromotionSetGroups();
                })
                .then(() => {
                    Shopware.State.commit('swPromotionDetail/setSetGroupIdsDelete', []);
                    this.isSaveSuccessful = true;
                    this.loadEntityData();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$tc('global.notification.notificationSaveErrorMessage', 0, {
                            entityName: this.promotion.name,
                        }),
                    });
                })
                .finally(() => {
                    this.cleanUpCodes(false, false);
                });
        },

        savePromotionSetGroups() {
            const setGroupIdsDelete = Shopware.State.get('swPromotionDetail').setGroupIdsDelete;

            if (setGroupIdsDelete !== null) {
                const deletePromises = setGroupIdsDelete.map((groupId) => {
                    return this.promotionGroupRepository.delete(groupId);
                });

                return Promise.all(deletePromises);
            }

            return Promise.resolve();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.promotion.v2.index' });
        },

        onCleanUpCodes(cleanUpIndividual, cleanUpFixed) {
            this.cleanUpCodes(cleanUpIndividual, cleanUpFixed);
        },

        cleanUpCodes(cleanUpIndividual, cleanUpFixed) {
            this.cleanUpIndividualCodes = cleanUpIndividual;
            this.cleanUpFixedCode = cleanUpFixed;
        },

        onGenerateIndividualCodesFinish() {
            this.savePromotion();
        },

        onDeleteIndividualCodesFinish() {
            this.savePromotion();
        },
    },
};
