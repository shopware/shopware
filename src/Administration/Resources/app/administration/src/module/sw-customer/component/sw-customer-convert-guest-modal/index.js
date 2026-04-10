import './sw-customer-convert-guest-modal.scss'
import template from './sw-customer-convert-guest-modal.html.twig';
import errorConfig from "../../error-config.json";

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
        'GuestCustomerConvertService',
        'loadCustomer'
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
                await this.GuestCustomerConvertService.sendMail(this.customer.id);
                await this.loadCustomer();

                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detail.messageSaveSuccess', {
                        name: `${this.customer.firstName} ${this.customer.lastName}`
                    }),
                });
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.errors[0]?.detail || this.$t('sw-customer.detail.messageSaveError')
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
                await this.GuestCustomerConvertService.convert(this.customer.id, { password: this.password });
                await this.loadCustomer();

                this.onCancel();

                Shopware.Store.get('error').removeApiError(`customer.${this.customer.id}.convert`);

                this.createNotificationSuccess({
                    message: this.$t('sw-customer.detail.messageSaveSuccess', {
                        name: `${this.customer.firstName} ${this.customer.lastName}`,
                    }),
                });
            } catch (error) {
                const apiErrors = error?.response?.data?.errors ?? [];

                const emailError = apiErrors.find(
                    ({ code }) => code === 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE'
                );

                if (emailError) {
                    this.onCancel();

                    this.createNotificationError({
                        message: emailError.detail || this.$t('sw-customer.detail.messageSaveError'),
                    });

                    return;
                }

                const [firstError] = apiErrors;

                Shopware.Store.get('error').addApiError({
                    expression: `customer.${this.customer.id}.convert`,
                    error: new ShopwareError({
                        detail: firstError?.detail || this.$t('sw-customer.detail.messageSaveError'),
                        code:  firstError?.code || 'customer_convert',
                    }),
                });
            }
        }
    }
};
