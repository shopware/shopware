/**
 * @sw-package checkout
 */

import './sw-customer-convert-guest.scss'
import template from './sw-customer-convert-guest.html.twig';
import ShopwareError from "../../../../core/data/ShopwareError";
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    template,

    emits: ['modal-close'],

    inject: [
        'GuestCustomerConvertService'
    ],

    props: {
        customerId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            password: '',
        };
    },

    computed: {
        ...mapPropertyErrors('convert', [
            'password',
            'email'
        ]),
    },

    methods: {
        async sendRecoveryEmail() {
            try {
                await this.GuestCustomerConvertService.sendMail(this.customerId);
            } catch (exception) {
                this.$emit('modal-close');

                Shopware.Store.get('error').addApiError({
                    expression: `customerid`,
                    error: new ShopwareError(exception.response.data.errors[0]),
                });

                // this.createNotificationError({
                //     message: this.$tc('sw-customer.detail.notificationImitateCustomerErrorMessage'),
                // });
            }
        },

        onCancel() {
            this.$emit('modal-close');
        },

        async convert() {

                await this.GuestCustomerConvertService.convert(this.customerId, {
                    password: this.password
                });

        }
    }
};
