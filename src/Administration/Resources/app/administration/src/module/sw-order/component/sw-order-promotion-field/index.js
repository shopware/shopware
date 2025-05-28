import './sw-order-promotion-field.scss';
import template from './sw-order-promotion-field.html.twig';

/**
 * @sw-package checkout
 */
const { Store } = Shopware;
const { ChangesetGenerator } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        swOrderDetailOnLoadingChange: {
            from: 'swOrderDetailOnLoadingChange',
            default: null,
        },
        swOrderDetailOnError: {
            from: 'swOrderDetailOnError',
            default: null,
        },
        swOrderDetailOnReloadEntityData: {
            from: 'swOrderDetailOnReloadEntityData',
            default: null,
        },
        swOrderDetailOnSaveAndReload: {
            from: 'swOrderDetailOnSaveAndReload',
            default: null,
        },
        swOrderDetailHandleCartErrors: {
            from: 'swOrderDetailHandleCartErrors',
            default: null,
        },
        repositoryFactory: {
            from: 'repositoryFactory',
            default: null,
        },
        orderService: {
            from: 'orderService',
            default: null,
        },
        acl: {
            from: 'acl',
            default: null,
        },
    },

    emits: [
        'error',
        'loading-change',
        'reload-entity-data',
        'save-and-reload',
    ],

    mixins: [
        'notification',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            promotionError: null,
            disabledAutoPromotions: false,
            promotionUpdates: [],
        };
    },

    computed: {
        order() {
            return Store.get('swOrderDetail').order;
        },

        isOrderLoading: () => Store.get('swOrderDetail').isLoading,

        versionContext() {
            return Store.get('swOrderDetail').versionContext;
        },

        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        hasLineItem() {
            return this.order.lineItems.some((item) => item.hasOwnProperty('id'));
        },

        currency() {
            return this.order.currency;
        },

        manualPromotions() {
            return this.order.lineItems.filter((item) => item.type === 'promotion' && item.referencedId !== null);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        automaticPromotions() {
            return this.order.lineItems.filter((item) => item.type === 'promotion' && item.referencedId === null);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        promotionCodeTags: {
            get() {
                return this.manualPromotions.map((item) => item.payload);
            },

            set(newValue) {
                const old = this.manualPromotions;

                this.promotionError = null;

                if (newValue.length < old.length) {
                    return;
                }

                const promotionCodeLength = old.length;
                const latestTag = newValue[promotionCodeLength];

                if (newValue.length > old.length) {
                    this.onSubmitCode(latestTag.code);
                }

                if (promotionCodeLength > 0 && latestTag.isInvalid) {
                    this.promotionError = {
                        detail: this.$tc('sw-order.createBase.textInvalidPromotionCode'),
                    };
                }
            },
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        hasAutomaticPromotions() {
            return this.automaticPromotions.length > 0;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        changesetGenerator() {
            return new ChangesetGenerator();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         *
         * @returns {boolean}
         */
        hasOrderUnsavedChanges() {
            return this.changesetGenerator.generate(this.order).changes !== null;
        },

        promotionsRemoved() {
            return this.promotionUpdates.filter((e) => e.messageKey === 'promotion-discount-deleted');
        },

        promotionsAdded() {
            return this.promotionUpdates.filter((e) => e.messageKey === 'promotion-discount-added');
        },
    },

    watch: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         *
         * Validate if switch can be toggled
         */
        disabledAutoPromotions(newState, oldState) {
            // To prevent recursion when value is set in next tick
            if (oldState === this.hasAutomaticPromotions) {
                return;
            }

            this.toggleAutomaticPromotions(newState);
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        automaticPromotions() {
            // Sync value with database
            this.disabledAutoPromotions = !this.hasAutomaticPromotions;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        createdComponent() {
            this.disabledAutoPromotions = !this.hasAutomaticPromotions;
        },

        emitEntityData() {
            if (this.swOrderDetailOnReloadEntityData) {
                this.swOrderDetailOnReloadEntityData();
            } else {
                this.$emit('reload-entity-data');
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        emitLoadingChange(state) {
            Shopware.Store.get('swOrderDetail').setLoading([
                'recalculation',
                state,
            ]);
        },

        /**
         * To prevent losing unsaved changes on reloading the order,
         * we need to save the **versioned** order beforehand.
         */
        async saveAndReload(afterSaveFn = null) {
            if (this.swOrderDetailOnSaveAndReload) {
                await this.swOrderDetailOnSaveAndReload(afterSaveFn);
            } else {
                this.$emit('save-and-reload', afterSaveFn);
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        handleUnsavedOrderChangesResponse() {
            this.createNotificationWarning({
                message: this.$tc('sw-order.detailBase.textUnsavedChanges', 0),
            });
        },

        handleError(error) {
            Shopware.Store.get('swOrderDetail').setLoading([
                'recalculation',
                false,
            ]);

            if (this.swOrderDetailOnError) {
                this.swOrderDetailOnError(error);
            } else {
                this.$emit('error', error);
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement. See `applyAutomaticPromotions` for an alternative
         */
        deleteAutomaticPromotions() {
            if (this.automaticPromotions.length === 0) {
                return Promise.resolve();
            }

            const deletionPromises = this.automaticPromotions.map((promotion) => {
                return this.orderLineItemRepository.delete(promotion.id, this.versionContext);
            });

            return Promise.all(deletionPromises)
                .then(() => {
                    this.automaticPromotions.forEach((promotion) => {
                        this.createNotificationSuccess({
                            message: this.$tc('sw-order.detailBase.textPromotionRemoved', { promotion: promotion.label }, 0),
                        });
                    });
                })
                .catch(this.handleError.bind(this));
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement. See `applyAutomaticPromotions` for an alternative
         */
        async toggleAutomaticPromotions(state) {
            if (this.hasOrderUnsavedChanges) {
                this.handleUnsavedOrderChangesResponse();

                this.$nextTick(() => {
                    this.disabledAutoPromotions = !state;
                });

                return Promise.resolve();
            }

            Shopware.Store.get('swOrderDetail').setLoading([
                'recalculation',
                true,
            ]);

            await this.saveAndReload();
            await this.deleteAutomaticPromotions();

            return this.orderService
                .toggleAutomaticPromotions(this.order.id, this.order.versionId, state)
                .then(this.handlePromotionResponse.bind(this))
                .catch(this.handleError.bind(this));
        },

        async applyAutomaticPromotions() {
            await this.saveAndReload(() =>
                this.orderService
                    .applyAutomaticPromotions(this.order.id, this.order.versionId)
                    .then(this.handlePromotionResponse.bind(this))
                    .catch(this.handleError.bind(this)),
            );
        },

        async onSubmitCode(code) {
            this.emitLoadingChange(true);

            return this.saveAndReload(() =>
                this.orderService
                    .addPromotionToOrder(this.order.id, this.order.versionId, code)
                    .then(this.handlePromotionResponse.bind(this))
                    .catch(this.handleError.bind(this)),
            );
        },

        handlePromotionResponse(response) {
            this.emitEntityData();
            Shopware.Store.get('swOrderDetail').setLoading([
                'recalculation',
                false,
            ]);

            if (typeof response?.data?.errors !== 'object') {
                return;
            }

            const [
                errors,
                promotionErrors,
            ] = (Array.isArray(response.data.errors) ? response.data.errors : Object.values(response.data.errors)).reduce(
                (
                    [
                        general,
                        promotion,
                    ],
                    e,
                ) => {
                    return [
                        'promotion-discount-deleted',
                        'promotion-discount-added',
                    ].includes(e.messageKey)
                        ? [
                              general,
                              [
                                  ...promotion,
                                  e,
                              ],
                          ]
                        : [
                              [
                                  ...general,
                                  e,
                              ],
                              promotion,
                          ];
                },
                [
                    [],
                    [],
                ],
            );

            this.promotionUpdates = promotionErrors;
            response.data.errors = errors;

            if (this.swOrderDetailHandleCartErrors) {
                this.swOrderDetailHandleCartErrors(response);
                return;
            }

            Object.values(response.data.errors).forEach((value) => {
                switch (value.level) {
                    case 0: {
                        this.createNotificationInfo({
                            message: value.message,
                        });
                        break;
                    }

                    case 10: {
                        this.createNotificationWarning({
                            message: value.message,
                        });
                        break;
                    }

                    default: {
                        this.createNotificationError({
                            message: value.message,
                        });
                        break;
                    }
                }
            });
        },

        async onRemoveExistingCode(removedItem) {
            Shopware.Store.get('swOrderDetail').setLoading([
                'recalculation',
                true,
            ]);

            const lineItem = this.order.lineItems.find((item) => {
                return item.type === 'promotion' && item.payload.code === removedItem.code;
            });

            await this.saveAndReload();

            return this.orderLineItemRepository
                .delete(lineItem.id, this.versionContext)
                .then(this.emitEntityData.bind(this))
                .catch(this.handleError.bind(this));
        },

        dismissPromotionUpdates() {
            this.promotionUpdates = [];
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        getLineItemByPromotionCode(code) {
            return this.order.lineItems.find((item) => {
                return item.type === 'promotion' && item.payload.code === code;
            });
        },
    },
};
