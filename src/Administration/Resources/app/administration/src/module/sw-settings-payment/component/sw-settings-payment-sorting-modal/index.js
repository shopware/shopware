import template from './sw-settings-payment-sorting-modal.html.twig';
import './sw-settings-payment-sorting-modal.scss';

const { Mixin } = Shopware;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'feature',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        paymentMethods: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            isSaving: false,
            originalPaymentMethods: [...this.paymentMethods],
            sortedPaymentMethods: [...this.paymentMethods],
            scrollOnDragConf: {
                speed: 50,
                margin: 130,
                accelerationMargin: -10,
            },
        };
    },

    computed: {
        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },

        applyChanges() {
            this.isSaving = true;

            this.sortedPaymentMethods.map((paymentMethod, index) => {
                paymentMethod.position = index + 1;
                return paymentMethod;
            });

            return this.paymentMethodRepository.saveAll(this.sortedPaymentMethods, Shopware.Context.api)
                .then(() => {
                    this.isSaving = false;
                    this.$emit('modal-close');
                    this.$emit('modal-save');

                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-payment.sorting-modal.saveSuccessful'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-payment.sorting-modal.errorMessage'),
                    });
                });
        },

        onSort(sortedItems) {
            this.sortedPaymentMethods = sortedItems;
        },

        isShopwareDefaultPaymentMethod(paymentMethod) {
            const defaultPaymentMethods = [
                'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\DebitPayment',
                'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\InvoicePayment',
                'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\CashPayment',
                'Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment',
            ];

            return defaultPaymentMethods.includes(paymentMethod.handlerIdentifier);
        },
    },
};
