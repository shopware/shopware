import type Repository from 'src/core/data/repository.data';
import type { Cart, PromotionCodeTag } from '../../order.types';
import '../../store/order.store';
import template from './sw-order-create.html.twig';
import './sw-order-create.scss';

/**
 * @sw-package checkout
 */

const { Context, Store, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): {
        isLoading: boolean;
        isSaveSuccessful: boolean;
        showInvalidCodeModal: boolean;
        showRemindPaymentModal: boolean;
        remindPaymentModalLoading: boolean;
        orderId: string | null;
        orderTransaction: { id: string; paymentMethodId: string } | null;
        paymentMethodName: string;
    } {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            showInvalidCodeModal: false,
            showRemindPaymentModal: false,
            remindPaymentModalLoading: false,
            orderId: null,
            orderTransaction: null,
            paymentMethodName: '',
        };
    },

    computed: {
        customer(): Entity<'customer'> | null {
            return Store.get('swOrder').customer;
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        invalidPromotionCodes(): PromotionCodeTag[] {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return Store.get('swOrder').invalidPromotionCodes;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use orderValidateErrorMessage() instead.
         */
        isSaveOrderValid(): boolean {
            return (this.customer &&
                this.cart.token &&
                this.cart.lineItems.length &&
                !this.invalidPromotionCodes.length) as boolean;
        },

        orderValidateErrorMessage(): string | null {
            if (!this.customer) {
                return this.$tc('sw-order.create.saveError.noCustomer');
            }

            if (!this.cart.token) {
                return this.$tc('sw-order.create.saveError.noCart');
            }

            if (this.cart.lineItems.length === 0) {
                return this.$tc('sw-order.create.saveError.noLineItems');
            }

            if (this.invalidPromotionCodes.length > 0) {
                return this.$tc('sw-order.create.saveError.invalidPromotionCodes');
            }

            return null;
        },

        paymentMethodRepository(): Repository<'payment_method'> {
            return this.repositoryFactory.create('payment_method');
        },

        showInitialModal(): boolean {
            return this.$route.name === 'sw.order.create.initial';
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            // set language to system language
            if (!Store.get('context').isSystemDefaultLanguage) {
                Store.get('context').resetLanguageToDefault();
            }
        },

        redirectToOrderList(): void {
            void this.$router.push({ name: 'sw.order.index' });
        },

        saveFinish(): void {
            if (!this.orderId) {
                return;
            }

            this.isSaveSuccessful = false;
            Shopware.Store.get('context').api.languageId =
                localStorage.getItem('sw-admin-current-language') || Shopware.Defaults.systemLanguageId;
            void this.$router.push({
                name: 'sw.order.detail',
                params: { id: this.orderId },
            });
        },

        async onSaveOrder(): Promise<void> {
            if (this.orderValidateErrorMessage) {
                if (this.invalidPromotionCodes.length) {
                    this.openInvalidCodeModal();
                }

                this.showError(this.orderValidateErrorMessage);
                return;
            }

            this.isLoading = true;
            this.isSaveSuccessful = false;

            try {
                const { data } = (await Store.get('swOrder').saveOrder({
                    salesChannelId: this.customer!.salesChannelId,
                    contextToken: this.cart.token,
                })) as {
                    data: {
                        id: string;
                        transactions: Array<{ id: string; paymentMethodId: string }>;
                    };
                };

                const [transaction] = data?.transactions || [];

                if (!transaction) {
                    throw new Error(this.$tc('sw-order.create.saveError.noTransactionReturned'));
                }

                this.orderId = data?.id;
                this.orderTransaction = transaction;

                await this.fetchPaymentMethodName();

                this.isSaveSuccessful = true;
                this.showRemindPaymentModal = true;
            } catch (error) {
                this.showError(error);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPaymentMethodName(): Promise<void> {
            if (!this.orderTransaction) {
                return;
            }

            const method = await this.paymentMethodRepository.get(
                this.orderTransaction.paymentMethodId,
                Context.api,
                new Criteria(1, 1),
            );

            this.paymentMethodName = method?.translated?.distinguishableName ?? '';
        },

        onCancelOrder() {
            if (this.customer === null || this.cart === null) {
                this.redirectToOrderList();
                return;
            }

            void Store.get('swOrder')
                .cancelCart({
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                })
                .then(() => this.redirectToOrderList());
        },

        showError(error: unknown = null) {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
            const errorMessage = error?.response?.data?.errors?.[0]?.detail || null;

            this.createNotificationError({
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                message: errorMessage || this.$tc('sw-order.create.messageSaveError'),
            });
        },

        openInvalidCodeModal() {
            this.showInvalidCodeModal = true;
        },

        closeInvalidCodeModal() {
            this.showInvalidCodeModal = false;
        },

        removeInvalidCode() {
            Store.get('swOrder').removeInvalidPromotionCodes();
            this.closeInvalidCodeModal();
        },

        onRemindPaymentModalClose() {
            this.isSaveSuccessful = true;

            this.showRemindPaymentModal = false;
        },

        onRemindCustomer() {
            this.remindPaymentModalLoading = true;

            if (!this.orderTransaction) return;

            void Store.get('swOrder')
                .remindPayment({
                    orderTransactionId: this.orderTransaction.id,
                })
                .then(() => {
                    this.remindPaymentModalLoading = false;

                    this.onRemindPaymentModalClose();
                });
        },
    },
});
