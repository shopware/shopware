import template from './sw-promotion-detail.html.twig';
import errorConfig from './error-config.json';
import swPromotionDetailState from './state';
import IndividualCodeGenerator from '../../service/individual-code-generator.service';
import entityHydrator from '../../helper/promotion-entity-hydrator.helper';

const { Component, Mixin, StateDeprecated } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-promotion-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('promotion')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        promotionId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            isSaveSuccessful: false,
            saveCallbacks: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
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

        languageStore() {
            return StateDeprecated.getStore('language');
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },
        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        promotion: {
            get() {
                return Shopware.State.get('swPromotionDetail').promotion;
            },
            set(promotion) {
                Shopware.State.commit('swPromotionDetail/setPromotion', promotion);
            }
        },

        isLoading: {
            get() {
                return Shopware.State.get('swPromotionDetail').isLoading;
            },
            set(isLoading) {
                Shopware.State.commit('swPromotionDetail/setIsLoading', isLoading);
            }
        },

        discounts() {
            return Shopware.State.get('swPromotionDetail').discounts;
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

        ...mapPageErrors(errorConfig)

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

    watch: {
        promotionId() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (!this.promotionId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
                this.promotion = this.promotionRepository.create(Shopware.Context.api);
                // hydrate and extend promotion with additional data
                entityHydrator.hydrate(this.promotion);
                this.isLoading = false;
                return;
            }
            this.loadEntityData();

            this.$root.$on('promotion-save-start', this.onShouldSave);
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
                    this.discounts.source
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
                        Shopware.Context.api
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
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage',
                        0,
                        { entityName: this.promotion.name }
                    )
                });
                throw error;
            }

            const discounts = this.discounts === null ? this.promotion.discounts : this.discounts;
            const discountRepository = this.repositoryFactory.create(
                discounts.entity,
                discounts.source
            );

            return this.savePromotionAssociations().then(() => {
                // first save our discounts
                return discountRepository.sync(discounts, discounts.context).then(() => {
                    // finally save our promotion
                    return this.promotionRepository.save(this.promotion, Shopware.Context.api)
                        .then(() => {
                            this.isSaveSuccessful = true;
                            const criteria = new Criteria(1, 1);
                            criteria.addAssociation('salesChannels');

                            return this.promotionRepository.get(
                                this.promotion.id,
                                Shopware.Context.api, criteria
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
                                title: this.$tc('global.default.error'),
                                message: this.$tc(
                                    'global.notification.notificationSaveErrorMessage',
                                    0,
                                    { entityName: this.promotion.name }
                                )
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
                this.promotion.personaCustomers.source
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
                    this.promotionGroupRepository.delete(groupId, Shopware.Context.api);
                });
            }

            // reset our helper "delta" arrays
            Shopware.State.commit('swPromotionDetail/setPersonaCustomerIdsAdd', []);
            Shopware.State.commit('swPromotionDetail/setPersonaCustomerIdsDelete', []);
            Shopware.State.commit('swPromotionDetail/setSetGroupIdsDelete', []);
        },

        onCancel() {
            this.$router.push({ name: 'sw.promotion.index' });
        }
    }
});
