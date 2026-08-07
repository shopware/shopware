import './sw-customer-convert-guest-modal.scss';
import template from './sw-customer-convert-guest-modal.html.twig';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { Mixin } = Shopware;
const { ShopwareError } = Shopware.Classes;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['modal-close'],

    inject: [
        'guestCustomerConvertService',
        'loadCustomer',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            password: '',
        };
    },

    computed: {
        ...mapPropertyErrors('customer', [
            ...errorConfig['sw.customer.detail.base'].customer,
        ]),
    },

    methods: {
        async sendRecoveryEmail() {
            try {
                await this.guestCustomerConvertService.convert(this.customer.id);
                await this.loadCustomer();

                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detail.messageSaveSuccess', {
                        name: `${this.customer.firstName} ${this.customer.lastName}`,
                    }),
                });
            } catch (error) {
                this.handleConvertErrors(error);
            } finally {
                this.onCancel();
            }
        },

        onCancel() {
            Shopware.Store.get('error').removeApiError(`customer.${this.customer.id}.convert`);

            this.$emit('modal-close');
        },

        async convert() {
            try {
                await this.guestCustomerConvertService.convert(this.customer.id, { password: this.password });
                await this.loadCustomer();

                this.onCancel();

                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detail.messageSaveSuccess', {
                        name: `${this.customer.firstName} ${this.customer.lastName}`,
                    }),
                });
            } catch (error) {
                this.handleConvertErrors(error);
            }
        },

        handleConvertErrors(error) {
            const errors = error?.response?.data?.errors ?? [];
            const errorStore = Shopware.Store.get('error');
            const expression = `customer.${this.customer.id}.convert`;

            const errorMap = {
                'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE': {
                    message: this.$t('sw-customer.error.VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE'),
                    apiError: false,
                },

                'VIOLATION::TOO_LONG_ERROR': {
                    message: this.$t('sw-customer.error.VIOLATION::PASSWORD_IS_TOO_LONG'),
                    apiError: true,
                },

                'VIOLATION::TOO_SHORT_ERROR': {
                    message: this.$t('sw-customer.error.VIOLATION::PASSWORD_IS_TOO_SHORT'),
                    apiError: true,
                },
            };

            if (!errors.length) {
                this.createNotificationError({
                    message: this.$t('sw-customer.detail.messageSaveError'),
                });

                return;
            }

            errors.forEach((e) => {
                const mappedError = errorMap[e?.code];

                const message = mappedError?.message || e?.detail || this.$t('sw-customer.detail.messageSaveError');

                this.createNotificationError({
                    message,
                });

                if (mappedError?.apiError) {
                    errorStore.addApiError({
                        expression,
                        error: new ShopwareError({
                            code: e?.code || 'customer_convert',
                            detail: message,
                        }),
                    });
                }
            });
        },
    },
};
