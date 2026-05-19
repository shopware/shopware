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
                const [firstError] = error?.response?.data?.errors ?? [];

                let message = firstError?.detail || this.$t('sw-customer.detail.messageSaveError');

                if (firstError?.code === 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE') {
                    message = this.$t('sw-customer.error.VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE');
                }

                this.createNotificationError({
                    message,
                });
            } finally {
                this.onCancel();
            }
        },

        onCancel() {
            this.$emit('modal-close');
        },

        async convert() {
            try {
                await this.guestCustomerConvertService.convert(this.customer.id, { password: this.password });
                await this.loadCustomer();

                this.onCancel();

                Shopware.Store.get('error').removeApiError(`customer.${this.customer.id}.convert`);

                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detail.messageSaveSuccess', {
                        name: `${this.customer.firstName} ${this.customer.lastName}`,
                    }),
                });
            } catch (error) {
                const [firstError] = error?.response?.data?.errors ?? [];

                const expression = `customer.${this.customer.id}.convert`;
                const errorStore = Shopware.Store.get('error');

                let detailMessage;

                switch (firstError?.code) {
                    case 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE':
                        this.onCancel();

                        errorStore.removeApiError(`customer.${this.customer.id}.convert`);

                        this.createNotificationError({
                            message: this.$t('sw-customer.error.VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE'),
                        });

                        return;

                    case 'VIOLATION::TOO_LONG_ERROR':
                        detailMessage = this.$t('sw-customer.error.VIOLATION::PASSWORD_IS_TOO_LONG');
                        break;

                    case 'VIOLATION::TOO_SHORT_ERROR':
                        detailMessage = this.$t('sw-customer.error.VIOLATION::PASSWORD_IS_TOO_SHORT');
                        break;

                    default:
                        detailMessage = firstError?.detail || this.$t('sw-customer.detail.messageSaveError');
                }

                errorStore.addApiError({
                    expression,
                    error: new ShopwareError({
                        detail: detailMessage,
                        code: firstError?.code || 'customer_convert',
                    }),
                });
            }
        },
    },
};
