import './sw-order-promotion-field.scss';
import template from './sw-order-promotion-field.html.twig';

const { Component } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-order-promotion-field', {
    template,

    inject: [
        'repositoryFactory',
        'orderService',
        'acl',
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
        };
    },

    computed: {
        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),

        orderLineItemRepository() {
            return this.repositoryFactory.create('order_line_item');
        },

        hasLineItem() {
            return this.order.lineItems.filter(item => item.hasOwnProperty('id')).length > 0;
        },

        currency() {
            return this.order.currency;
        },

        manualPromotions() {
            return this.order.lineItems.filter(item => item.type === 'promotion' && item.referencedId !== null);
        },

        automaticPromotions() {
            return this.order.lineItems.filter(item => item.type === 'promotion' && item.referencedId === null);
        },

        promotionCodeTags: {
            get() {
                return this.manualPromotions.map(item => item.payload);
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
                    this.promotionError = { detail: this.$tc('sw-order.createBase.textInvalidPromotionCode') };
                }
            },
        },

        disabledAutoPromotionVisibility: {
            get() {
                return !this.hasAutomaticPromotions;
            },
            set(state) {
                this.toggleAutomaticPromotions(state);
            },
        },

        hasAutomaticPromotions() {
            return this.automaticPromotions.length > 0;
        },
    },

    methods: {
        deleteAutomaticPromotions() {
            if (this.automaticPromotions.length === 0) {
                return Promise.resolve();
            }

            return this.orderLineItemRepository
                .syncDeleted(this.automaticPromotions.map(promotion => promotion.id), this.versionContext)
                .then(() => {
                    this.automaticPromotions.forEach((promotion) => {
                        this.createNotificationSuccess({
                            message: this.$tc('sw-order.detailBase.textPromotionRemoved', 0, {
                                promotion: promotion.label,
                            }),
                        });
                    });
                }).catch((error) => {
                    this.$emit('loading-change', false);
                    this.$emit('error', error);
                });
        },

        toggleAutomaticPromotions(state) {
            this.$emit('loading-change', true);

            this.deleteAutomaticPromotions().then(() => {
                return this.orderService.toggleAutomaticPromotions(
                    this.order.id,
                    this.order.versionId,
                    state,
                );
            }).then((response) => {
                this.handlePromotionResponse(response);
                this.$emit('reload-entity-data');
            }).catch((error) => {
                this.$emit('loading-change', false);
                this.$emit('error', error);
            });
        },

        onSubmitCode(code) {
            this.$emit('loading-change', true);

            this.orderService.addPromotionToOrder(
                this.order.id,
                this.order.versionId,
                code,
            ).then((response) => {
                this.handlePromotionResponse(response);
                this.$emit('reload-entity-data');
            }).catch((error) => {
                this.$emit('loading-change', false);
                this.$emit('error', error);
            });
        },

        handlePromotionResponse(response) {
            Object.values(response.data.errors).forEach((value) => {
                switch (value.level) {
                    case 0: {
                        this.createNotificationSuccess({
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

        onRemoveExistingCode(removedItem) {
            this.$emit('loading-change', true);

            const lineItem = this.order.lineItems.find((item) => {
                return item.type === 'promotion' && item.payload.code === removedItem.code;
            });

            this.orderLineItemRepository
                .delete(lineItem.id, this.versionContext)
                .then(() => {
                    this.$emit('reload-entity-data');
                })
                .catch((error) => {
                    this.$emit('loading-change', false);
                    this.$emit('error', error);
                });
        },

        getLineItemByPromotionCode(code) {
            return this.order.lineItems.find(item => {
                return item.type === 'promotion' && item.payload.code === code;
            });
        },
    },
});
