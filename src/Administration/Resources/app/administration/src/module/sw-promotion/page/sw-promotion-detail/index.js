import template from './sw-promotion-detail.html.twig';
import errorConfig from './error-config.json';
import swPromotionDetailState from './state';
import IndividualCodeGenerator from '../../service/individual-code-generator.service';
import entityHydrator from '../../helper/promotion-entity-hydrator.helper';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-detail', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
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
            default: null,
        },
    },

    data() {
        return {
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

        promotionGroupRepository() {
            return this.repositoryFactory.create('promotion_setgroup');
        },

        repositoryIndividualCodes() {
            return this.repositoryFactory.create('promotion_individual_code');
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

        promotion: {
            get() {
                return Shopware.State.get('swPromotionDetail').promotion;
            },
            set(promotion) {
                Shopware.State.commit('swPromotionDetail/setPromotion', promotion);
            },
        },

        isLoading: {
            get() {
                return Shopware.State.get('swPromotionDetail').isLoading;
            },
            set(isLoading) {
                Shopware.State.commit('swPromotionDetail/setIsLoading', isLoading);
            },
        },

        discounts() {
            return Shopware.State.get('swPromotionDetail').promotion.discounts;
        },

        personaCustomerIdsAdd() {
            return Shopware.State.get('swPromotionDetail').personaCustomerIdsAdd;
        },

        personaCustomerIdsDelete() {
            return Shopware.State.get('swPromotionDetail').personaCustomerIdsDelete;
        },

        setGroupIdsDelete() {
            return Shopware.State.get('swPromotionDetail').setGroupIdsDelete;
        },

        ...mapPageErrors(errorConfig),

    },

    watch: {
        promotionId() {
            this.createdComponent();
        },
    },

    beforeCreate() {
        Shopware.State.registerModule('swPromotionDetail', swPromotionDetailState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swPromotionDetail');
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.$root.$on('promotion-save-start', this.onShouldSave);
            if (!this.promotionId) {
                Shopware.State.commit('context/resetLanguageToDefault');
                Shopware.State.commit('shopwareApps/setSelectedIds', []);

                this.promotion = this.promotionRepository.create();
                // hydrate and extend promotion with additional data
                entityHydrator.hydrate(this.promotion);
                this.isLoading = false;
                return;
            }

            Shopware.State.commit('shopwareApps/setSelectedIds', [this.promotionId]);
            this.loadEntityData();
        },

        destroyedComponent() {
            this.$root.$off('promotion-save-start', this.onShouldSave);
        },

        loadEntityData() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('salesChannels');

            this.promotionRepository.get(this.promotionId, Shopware.Context.api, criteria).then((promotion) => {
                this.promotion = promotion;
                // hydrate and extend promotion with additional data
                entityHydrator.hydrate(this.promotion);
                this.isLoading = false;
            });
        },

        abortOnLanguageChange() {
            if (this.promotionRepository.hasChanges(this.promotion)) {
                return true;
            }

            if (this.discounts !== null) {
                const discountRepository = this.repositoryFactory.create(
                    this.discounts.entity,
                    this.discounts.source,
                );

                return this.discounts.some((discount) => {
                    return discount.isNew() || discountRepository.hasChanges(discount);
                });
            }

            return false;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            this.isLoading = true;
            if (!this.promotionId) {
                return this.createPromotion();
            }

            return this.savePromotion();
        },

        onShouldSave() {
            this.onSave()
                .then(() => {
                    this.$root.$emit('promotion-save-success');
                })
                .catch(() => {
                    this.$root.$emit('promotion-save-error');
                });
        },

        createPromotion() {
            return this.savePromotion().then(() => {
                this.$router.push({ name: 'sw.promotion.detail', params: { id: this.promotion.id } });
            });
        },

        async savePromotion() {
            try {
                // first start by adjusting our promotion data
                // depending on some circumstances.
                // we need to ensure the consistency of our data depending on some settings.
                // it's planned to be integrated within the server side API, but for
                // now we adjust that data in here.
                if (this.promotion.useCodes && this.promotion.useIndividualCodes) {
                    this.promotion.code = null;
                } else if (this.promotion.useCodes && !this.promotion.useIndividualCodes) {
                    this.promotion.individualCodePattern = null;

                    const generator = new IndividualCodeGenerator(
                        this.promotion.id,
                        this.repositoryIndividualCodes,
                        Shopware.Context.api,
                    );

                    await generator.removeExistingCodes();
                } else if (!this.promotion.useCodes) {
                    // if we dont use codes in general,
                    // make sure to reset it to avoid interferences with other promotions and this code
                    this.promotion.code = null;
                }
            } catch (error) {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    ),
                });
                throw error;
            }

            const discounts = this.discounts;
            const discountRepository = this.repositoryFactory.create(
                discounts.entity,
                discounts.source,
            );

            return this.savePromotionAssociations().then(() => {
                // first save our discounts
                return discountRepository.sync(discounts, discounts.context).then(() => {
                    // finally save our promotion
                    return this.promotionRepository.save(this.promotion)
                        .then(() => {
                            this.isSaveSuccessful = true;
                            const criteria = new Criteria(1, 1);
                            criteria.addAssociation('salesChannels');

                            return this.promotionRepository.get(
                                this.promotion.id,
                                Shopware.Context.api, criteria,
                            ).then((promotion) => {
                                this.promotion = promotion;
                                // hydrate and extend promotion with additional data
                                entityHydrator.hydrate(this.promotion);
                                this.isLoading = false;
                            });
                        })
                        .catch((error) => {
                            this.isLoading = false;
                            this.createNotificationError({
                                message: this.$tc(
                                    'global.notification.unspecifiedSaveErrorMessage',
                                    0,
                                    { entityName: this.promotion.name },
                                ),
                            });
                            throw error;
                        });
                }).catch(() => {
                    this.isLoading = false;
                });
            });
        },

        async savePromotionAssociations() {
            const customerPersonaRepository = this.repositoryFactory.create(
                this.promotion.personaCustomers.entity,
                this.promotion.personaCustomers.source,
            );

            if (this.personaCustomerIdsDelete !== null) {
                await this.personaCustomerIdsDelete.forEach((customerId) => {
                    customerPersonaRepository.delete(customerId, Shopware.Context.api);
                });
            }

            if (this.personaCustomerIdsAdd !== null) {
                await this.personaCustomerIdsAdd.forEach((customerId) => {
                    customerPersonaRepository.assign(customerId, Shopware.Context.api);
                });
            }

            // remove deleted groups. UPSERT will be done automatically
            if (this.setGroupIdsDelete !== null) {
                await this.setGroupIdsDelete.forEach((groupId) => {
                    this.promotionGroupRepository.delete(groupId);
                });
            }

            // reset our helper "delta" arrays
            Shopware.State.commit('swPromotionDetail/setPersonaCustomerIdsAdd', []);
            Shopware.State.commit('swPromotionDetail/setPersonaCustomerIdsDelete', []);
            Shopware.State.commit('swPromotionDetail/setSetGroupIdsDelete', []);
        },

        onCancel() {
            this.$router.push({ name: 'sw.promotion.index' });
        },
    },
});
